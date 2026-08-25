<?php

namespace App\Services\AI\Providers;

use App\Contracts\AIProviderInterface;
use App\DTOs\FinancialCommandDTO;
use App\DTOs\IntentClassificationDTO;
use App\DTOs\ParsedTransactionDTO;
use App\DTOs\QueryParametersDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
    private string $apiKey;

    /**
     * Ordered fallback list — tried top-to-bottom when a model hits rate limits (429) or is unavailable (404).
     * Sorted by free-tier RPD (Requests Per Day) from highest to lowest.
     *
     * Model            RPD   RPM
     * gemini-3.1-flash-lite  500   15
     * gemini-3.5-flash-lite  500   15
     * gemini-2.5-flash-lite   20   10
     * gemini-2.5-flash        20    5
     * gemini-3.5-flash        20    5
     * gemini-flash-latest     20    5   (resolves to latest flash)
     */
    private array $fallbackModels;

    private string $baseApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');

        // Primary model from config, then fallback chain by RPD limit
        $configuredModel = config('services.gemini.model', 'gemini-3.1-flash-lite');

        $allFallbacks = [
            'gemini-3.1-flash-lite',   // 500 RPD
            'gemini-3.5-flash-lite',   // 500 RPD
            'gemini-2.5-flash-lite',   //  20 RPD
            'gemini-2.5-flash',        //  20 RPD
            'gemini-3.5-flash',        //  20 RPD
            'gemini-flash-latest',     //  20 RPD (alias)
        ];

        // Put configured model first, then the rest (deduped)
        $this->fallbackModels = array_values(array_unique(array_merge(
            [$configuredModel],
            $allFallbacks
        )));
    }

    // ─────────────────────────────────────────────────────
    //  Response Schemas (OpenAPI subset for Gemini)
    // ─────────────────────────────────────────────────────

    /**
     * Schema for classifyIntent — returns {intent, confidence}.
     */
    private function intentSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'intent' => [
                    'type' => 'STRING',
                    'enum' => [
                        'add_transaction',
                        'query_report',
                        'correction',
                        'delete_transaction',
                        'inspect_records',
                        'savings_advice',
                        'manage_records',
                        'greeting_smalltalk',
                        'unclear',
                    ],
                ],
                'confidence' => [
                    'type' => 'NUMBER',
                ],
            ],
            'required' => ['intent', 'confidence'],
        ];
    }

    /**
     * Schema for extractTransactions — returns array of transaction objects.
     */
    private function transactionSchema(): array
    {
        return [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'description' => ['type' => 'STRING'],
                    'amount' => ['type' => 'INTEGER'],
                    'type' => [
                        'type' => 'STRING',
                        'enum' => ['expense', 'income'],
                    ],
                    'category' => ['type' => 'STRING'],
                    'wallet' => ['type' => 'STRING', 'nullable' => true],
                    'date' => ['type' => 'STRING', 'nullable' => true],
                    'notes' => ['type' => 'STRING', 'nullable' => true],
                    'confidence' => ['type' => 'NUMBER'],
                    'needs_clarification' => ['type' => 'BOOLEAN'],
                    'clarification_reason' => ['type' => 'STRING', 'nullable' => true],
                ],
                'required' => ['description', 'amount', 'type', 'category', 'confidence', 'needs_clarification'],
            ],
        ];
    }

    /**
     * Schema for parseQuery — returns query parameters.
     */
    private function querySchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'query_type' => [
                    'type' => 'STRING',
                    'enum' => [
                        'total_by_category',
                        'total_by_period',
                        'trend_comparison',
                        'balance_prediction',
                        'top_spending',
                        'general_summary',
                    ],
                ],
                'period' => [
                    'type' => 'STRING',
                    'enum' => [
                        'this_month',
                        'last_month',
                        'this_week',
                        'last_week',
                        'today',
                        'custom',
                    ],
                ],
                'category_filter' => ['type' => 'STRING', 'nullable' => true],
                'date_from' => ['type' => 'STRING', 'nullable' => true],
                'date_to' => ['type' => 'STRING', 'nullable' => true],
            ],
            'required' => ['query_type', 'period'],
        ];
    }



    public function classifyIntent(string $message, array $context = []): IntentClassificationDTO
    {
        $systemPrompt = <<<PROMPT
Kamu adalah classifier pesan untuk aplikasi pencatat keuangan. Tugasmu HANYA menentukan intent dari pesan user.

Intent yang tersedia:
- "add_transaction": user ingin mencatat transaksi BARU yang belum ada di sistem (contoh: "isi bensin 200 ribu", "beli kopi 20rb", "gajian 5 juta", "makan siang 35rb")
- "query_report": user ingin laporan atau statistik keuangan (contoh: "bulan ini habis berapa?", "pengeluaran makan minggu ini", "ringkasan bulan ini", "total pengeluaran")
- "correction": user ingin MENGUBAH transaksi yang SUDAH ADA di sistem — ditandai kata: "yang tadi", "yang kemarin", "harusnya", "salah", "koreksi", "ubah", "ganti", "ralat", "bukan" (contoh: "yang kopi tadi harusnya Hiburan", "yang bensin salah harusnya 80rb", "koreksi yang tadi jadi 50rb", "bukan BCA tapi Cash")
- "delete_transaction": user ingin MENGHAPUS transaksi yang sudah ada (contoh: "hapus yang bensin tadi", "delete transaksi terakhir", "batalkan yang kopi", "hilangkan transaksi bensin", "hapus semua transaksi")
- "inspect_records": user ingin MELIHAT data yang ada tanpa mengubah apapun (contoh: "saldo BCA berapa?", "saldo semua wallet", "transaksi terakhir apa?", "daftar wallet", "daftar kategori", "budget makan bulan ini?", "riwayat transaksi", "cek saldo", "sisa budget", "sis budget", "sisa anggaran", "sis anggaran")
- "savings_advice": user ingin saran penghematan atau tips hemat keuangan (contoh: "kasih saran dong", "di mana bisa aku hemat?", "tips hemat", "gimana caranya aku bisa nabung?", "pengeluaranku boros di mana?")
- "manage_records": user ingin MEMBUAT, MENGUBAH, atau MENGATUR wallet/kategori/budget/saldo.
  Contoh klasik: "buat wallet Dana", "tambah kategori Investasi", "buat budget makan 2 juta", "rename wallet BCA jadi BCA Digital", "naikkan budget makan jadi 2,5 juta"
  Contoh SET SALDO wallet (PENTING — ini MANAGE bukan add_transaction):
  - "cash gw 1jt" / "saldo cash 1jt" / "set cash jadi 1 juta" → set saldo wallet Cash
  - "BCA 3jt" / "wallet BCA 3 juta" / "saldo BCA sekarang 3jt" → set saldo wallet BCA
  - "cash 1jt, BCA 3jt" / "cash 1jt sisanya di BCA" / "1jt di cash 3jt di BCA" → set saldo multi-wallet
  - "buat wallet BCA dengan saldo 5jt" / "tambah wallet Dana isi 2jt" → buat wallet + set saldo awal
- "greeting_smalltalk": sapaan atau obrolan ringan (contoh: "halo", "terima kasih", "siapa kamu?")
- "unclear": pesan tidak jelas atau tidak terkait keuangan

ATURAN PENTING — baca ini dengan seksama:
1. Jika pesan mengandung "yang tadi", "yang kemarin", "harusnya", "salah" = CORRECTION bukan add_transaction
2. Jika pesan mengandung "saldo", "cek", "daftar", "riwayat", "transaksi terakhir", "sisa budget", "sis budget", "sisa" = INSPECT bukan yang lain
3. Jika pesan mengandung "buat", "tambah", "rename", "naikkan", "turunkan" untuk wallet/kategori/budget = MANAGE
4. Jika pesan mengandung "hapus", "delete", "hilangkan" untuk transaksi = DELETE
5. add_transaction HANYA untuk transaksi yang benar-benar BARU, bukan referensi ke transaksi lama
6. Jika pesan menyebut NAMA WALLET + NOMINAL tanpa kata kerja transaksi = MANAGE (set saldo)
   - "cash 1jt" tanpa konteks belanja = MANAGE
   - "BCA 5jt" = MANAGE
   - "cash 1jt, BCA 3jt" = MANAGE
7. Bahasa gaul yang umum: "gw"=saya, "ge"=saya, "lo"=kamu, "doang"=hanya, "sementara"=sedangkan, "sisanya"=sisa

CONTOH NEGATIF (jangan salah klasifikasi):
- "yang kopi tadi harusnya Hiburan" → BUKAN add_transaction, ini CORRECTION
- "yang bensin salah, harusnya 80rb" → BUKAN add_transaction, ini CORRECTION
- "saldo semua wallet" → BUKAN query_report, ini INSPECT
- "daftar wallet" → BUKAN unclear, ini INSPECT
- "hapus yang bensin tadi" → BUKAN correction, ini DELETE
- "cash gw 1jt" → BUKAN add_transaction, ini MANAGE (set saldo wallet)
- "BCA 3jt" → BUKAN add_transaction, ini MANAGE (set saldo wallet)
- "cash 1jt sisanya BCA" → BUKAN add_transaction, ini MANAGE (set saldo multi-wallet)
PROMPT;

        $data = $this->callGeminiStructured($systemPrompt, $message, $this->intentSchema());

        return IntentClassificationDTO::fromAIResponse($data);
    }

    public function extractTransactions(string $message, array $categories, array $context = []): array
    {
        $categoryList = implode(', ', $categories);

        $systemPrompt = <<<PROMPT
Kamu adalah parser transaksi keuangan. Tugasmu mengekstrak transaksi dari pesan user.

Kategori yang tersedia: {$categoryList}

Aturan:
1. SELALU kembalikan array transaksi (walau hanya 1).
2. Pisahkan multi-transaksi berdasar kata penghubung: "sama", "terus", "juga", "dan", koma, baris baru.
3. Konversi angka: "20rb"/"20ribu"/"20k" = 20000, "200rb" = 200000, "1.5jt"/"1,5juta" = 1500000.
4. Default type: "expense". Gunakan "income" jika ada kata: "gajian", "dapat", "terima", "masuk", "transfer masuk", "dapet".
5. Pilih kategori dari daftar yang tersedia. Jika tidak yakin, gunakan "Lainnya".
6. Setiap transaksi punya confidence masing-masing (0.0-1.0).
7. Ambil deskripsi singkat dari konteks pesan.
8. Jika ada detail tambahan (tempat, alasan, catatan), masukkan ke field "notes".
   Contoh: "beli kopi 20rb di Starbucks bareng Budi" → notes = "di Starbucks bareng Budi"
9. Set needs_clarification = true jika:
   - Nominal tidak disebutkan atau ambigu
   - Kategori sangat tidak jelas
   - Pesan sangat ambigu
   Isi clarification_reason dengan alasan singkat jika needs_clarification = true.
10. Jika user menyebut nama wallet (e.g. "dari BCA", "pakai Dana", "cash"), isi field wallet.
11. Jika user menyebut tanggal (e.g. "kemarin", "tadi malam", "tanggal 15"), isi field date dalam format YYYY-MM-DD.
PROMPT;

        $data = $this->callGeminiStructured($systemPrompt, $message, $this->transactionSchema());

        // Ensure we always have an array of arrays
        if (isset($data['description'])) {
            $data = [$data];
        }

        return array_map(
            fn(array $item) => ParsedTransactionDTO::fromAIResponse($item),
            $data
        );
    }

    public function parseQuery(string $message, array $context = []): QueryParametersDTO
    {
        $today = now()->toDateString();
        $currentMonth = now()->format('Y-m');

        $systemPrompt = <<<PROMPT
Kamu adalah parser query keuangan. Tugasmu HANYA menentukan parameter query, BUKAN menjawab pertanyaan.

Hari ini: {$today}
Bulan ini: {$currentMonth}

Tipe query yang tersedia:
- "total_by_category": total pengeluaran per kategori
- "total_by_period": total pengeluaran per periode (hari/minggu/bulan)
- "trend_comparison": perbandingan antar periode (misal bulan ini vs bulan lalu)
- "balance_prediction": prediksi saldo
- "top_spending": pengeluaran terbesar
- "general_summary": ringkasan umum
PROMPT;

        $data = $this->callGeminiStructured($systemPrompt, $message, $this->querySchema());

        return QueryParametersDTO::fromAIResponse($data);
    }

    public function parseFinancialCommand(string $message, array $context = []): FinancialCommandDTO
    {
        $systemPrompt = <<<PROMPT
Kamu adalah parser perintah keuangan. Tugasmu mengubah pesan user menjadi structured command JSON.

ATURAN KETAT:
1. JANGAN pernah mengarang transaction_id, wallet_id, atau category_id
2. Untuk target transaksi, gunakan deskripsi/jumlah/tanggal yang disebut user
3. Untuk wallet/category, gunakan NAMA yang disebut user (backend yang resolve ke ID)
4. Konversi angka: "20rb"/"20ribu"/"20k" = 20000, "1,5jt"/"1.5juta" = 1500000, "ge 1jt"=1000000
5. Jika user tidak menyebut tanggal, jangan isi date
6. Jika user tidak menyebut wallet, jangan isi wallet
7. Confidence: 0.0-1.0 berdasar kejelasan pesan user
8. Bahasa gaul: "gw"=saya, "ge"=saya, "doang"=hanya, "sisanya"=sisa, "sementara"=sedangkan

ACTIONS yang tersedia:
- update_transaction: koreksi transaksi yang sudah ada
- delete_transaction: hapus transaksi
- create_wallet: buat wallet baru (tanpa saldo awal)
- create_wallet_with_balance: buat wallet baru SEKALIGUS set saldo awal
- rename_wallet: ubah nama wallet
- delete_wallet: hapus wallet yang ada
- set_wallet_balance: ubah/set saldo wallet yang sudah ada ke nilai tertentu
- set_multiple_wallet_balances: set saldo beberapa wallet sekaligus
- create_category: buat kategori baru
- rename_category: ubah nama kategori
- create_budget: buat SATU budget baru
- create_multiple_budgets: buat BEBERAPA budget sekaligus dalam satu pesan
- update_budget: ubah jumlah budget
- delete_budget: hapus budget

FORMAT OUTPUT (JSON ketat):
{"action":"...","target":{"description":null,"amount":null,"category":null,"wallet":null,"date":null},"changes":{"amount":null,"category":null,"wallet":null,"description":null,"date":null,"type":null},"data":{"name":null,"type":null,"amount":null,"category":null,"period":null,"old_name":null,"new_name":null,"wallets":null,"budgets":null},"confidence":0.0}

Isi HANYA field yang relevan. Sisanya null.

KATEGORI YANG ADA DI SISTEM (gunakan nama yang paling sesuai):
- Makan & Minum: konsumsi, makan, minum, ngopi, kopi, resto, warung, nasgor, soto
- Transport: bensin, transport, ojek, grab, gojek, parkir, tol, bbm, bahan bakar
- Belanja: belanja, beli, shop, toko, supermarket
- Hiburan: hiburan, game, nonton, bioskop, streaming, netflix, spotify
- Kesehatan: obat, dokter, rs, kesehatan, apotek
- Pendidikan: sekolah, kuliah, kursus, buku, pendidikan
- Tagihan: tagihan, listrik, air, wifi, internet, pulsa, token
- Lainnya: lainnya, lain, misc
- Gaji: gaji, gajian, salary
- Bonus/THR: bonus, thr, reward
- Freelance/Sampingan: freelance, project, sampingan, bisnis

Singkatan/slang umum: "utk"=untuk, "utk bensin"=Transport, "konsumsi"=Makan & Minum, "utk makan"=Makan & Minum

CONTOH:

User: "yang kopi tadi harusnya 75 ribu"
{"action":"update_transaction","target":{"description":"kopi"},"changes":{"amount":75000},"data":null,"confidence":0.9}

User: "yang bensin tadi bukan Cash, tapi BCA"
{"action":"update_transaction","target":{"description":"bensin"},"changes":{"wallet":"BCA"},"data":null,"confidence":0.9}

User: "hapus transaksi makan tadi"
{"action":"delete_transaction","target":{"description":"makan"},"changes":null,"data":null,"confidence":0.85}

User: "buat wallet Dana"
{"action":"create_wallet","target":null,"changes":null,"data":{"name":"Dana"},"confidence":0.95}

User: "hapus wallet pegangan" / "delete wallet BCA"
{"action":"delete_wallet","target":null,"changes":null,"data":{"name":"pegangan"},"confidence":0.95}

User: "buat wallet BCA dengan saldo 5jt"
{"action":"create_wallet_with_balance","target":null,"changes":null,"data":{"name":"BCA","amount":5000000},"confidence":0.95}

User: "saldo cash gw 1jt" / "cash ge 1jt" / "set cash jadi 1 juta"
{"action":"set_wallet_balance","target":null,"changes":null,"data":{"name":"Cash","amount":1000000},"confidence":0.9}

User: "BCA 3jt" / "saldo BCA sekarang 3jt" / "wallet BCA 3 juta"
{"action":"set_wallet_balance","target":null,"changes":null,"data":{"name":"BCA","amount":3000000},"confidence":0.85}

User: "cash 1jt sisanya di BCA 3jt" / "cash 1jt, BCA 3jt" / "1jt di cash 3jt di BCA"
{"action":"set_multiple_wallet_balances","target":null,"changes":null,"data":{"wallets":[{"name":"Cash","amount":1000000},{"name":"BCA","amount":3000000}]},"confidence":0.9}

User: "buat agar cash ge 1jt doang sementara tambah wallet BCA 3jt"
{"action":"set_multiple_wallet_balances","target":null,"changes":null,"data":{"wallets":[{"name":"Cash","amount":1000000},{"name":"BCA","amount":3000000}]},"confidence":0.85}

User: "buat budget makan 2 juta bulan ini"
{"action":"create_budget","target":null,"changes":null,"data":{"category":"Makan & Minum","amount":2000000,"period":"monthly"},"confidence":0.9}

User: "buat budget utk Konsumsi 800rb\nUtk bensin 200rb" / "budget makan 800rb sama transport 200rb"
{"action":"create_multiple_budgets","target":null,"changes":null,"data":{"budgets":[{"category":"Makan & Minum","amount":800000,"period":"monthly"},{"category":"Transport","amount":200000,"period":"monthly"}]},"confidence":0.9}

Jangan tambahkan penjelasan apapun di luar JSON.
PROMPT;

        // Build context string with recent transactions, wallets, categories
        $contextParts = ['[CONTEXT]'];

        if (!empty($context['recent_transactions'])) {
            $contextParts[] = 'Transaksi terbaru:';
            foreach ($context['recent_transactions'] as $i => $tx) {
                $amount = number_format($tx['amount'] ?? 0, 0, ',', '.');
                $date = $tx['date'] ?? 'hari ini';
                $contextParts[] = ($i + 1) . ". {$tx['description']} — Rp{$amount} — {$tx['category']} — {$tx['wallet']} — {$date}";
            }
        }

        if (!empty($context['wallets'])) {
            $contextParts[] = 'Wallet: ' . implode(', ', $context['wallets']);
        }

        if (!empty($context['categories_expense'])) {
            $contextParts[] = 'Kategori expense: ' . implode(', ', $context['categories_expense']);
        }

        if (!empty($context['categories_income'])) {
            $contextParts[] = 'Kategori income: ' . implode(', ', $context['categories_income']);
        }

        $contextParts[] = '';
        $contextParts[] = '[PESAN USER]';
        $contextParts[] = $message;

        $userMessage = implode("\n", $contextParts);

        // Use free-text JSON mode for this complex nested command structure
        $response = $this->callGemini($systemPrompt, $userMessage);
        $data = $this->parseJsonResponse($response);

        return FinancialCommandDTO::fromAIResponse($data, $message);
    }

    public function formatResponse(string $type, array $data, array $context = []): string
    {
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);

        $systemPrompt = <<<PROMPT
Kamu adalah formatter respons untuk aplikasi keuangan via WhatsApp.

Aturan MUTLAK:
1. JANGAN PERNAH mengarang atau mengubah angka. Semua angka HARUS berasal dari data yang diberikan.
2. Gunakan Bahasa Indonesia yang ramah, singkat, dan casual (seperti ngobrol sama teman).
3. Gunakan emoji yang relevan tapi jangan berlebihan (1-3 emoji per pesan).
4. Format angka uang: Rp50.000, Rp1.500.000 (titik sebagai pemisah ribuan).
5. Jangan terlalu formal, tapi tetap informatif.
6. Untuk konfirmasi transaksi, sebutkan semua yang dicatat.
7. Jika ada transaksi dengan confidence rendah, tambahkan catatan bahwa user bisa koreksi.

FORMAT KHUSUS untuk prediksi saldo (jika data mengandung key "trend"):
- Jika trend = "burning": sampaikan dengan nada perhatian bahwa saldo berkurang, sebutkan estimasi hari tersisa dan tanggal habis, saran hemat.
  Contoh gaya: "Saldo kamu Rp1.000.000 diperkirakan habis dalam 15 hari (tanggal 26 Agu) dengan laju bersih Rp70.000/hari. Coba kurangi pengeluaranmu ya! 💸"
- Jika trend = "saving": sampaikan dengan nada positif bahwa saldo bertambah, sebutkan daily savings rate dan prediksi akhir bulan.
  Contoh gaya: "Keren! Saldo kamu bertambah rata-rata Rp20.000/hari. Akhir bulan ini saldo diprediksi naik menjadi Rp1.500.000. Pertahankan! 💰"
- Jika trend = "stable": sampaikan bahwa keuangan seimbang.
  Contoh gaya: "Keuanganmu stabil bulan ini. Pengeluaran dan pemasukan seimbang! Selisihnya hanya Rp2.000/hari. ⚖️"
- Jika trend = "unknown": minta user untuk mencatat beberapa transaksi dulu.
  Contoh gaya: "Aku belum punya cukup data buat prediksi nih 🤔 Coba catat beberapa transaksi dulu ya!"

FORMAT KHUSUS untuk saran penghematan (jika data mengandung key "suggestions"):
- Tampilkan maksimal 3 saran konkret berdasarkan kategori terboros.
- Untuk setiap kategori, sebutkan pengeluaran bulan ini, perbandingan bulan lalu (naik/turun), dan potensi hemat jika dikurangi 20%.
- Gunakan nada positif dan memotivasi, bukan menghakimi.
- Selalu tutup dengan total estimasi penghematan jika semua saran diterapkan.
- Contoh gaya: "💡 Bulan ini kamu paling boros di Ngopi (Rp500.000, naik 20% dari bulan lalu). Kalau dikurangi 20%, kamu bisa hemat Rp100.000/bulan!"
- Jika tidak ada data transaksi (has_data = false): "Aku belum punya data cukup untuk kasih saran nih 🤔 Coba catat transaksi dulu ya!"

FORMAT KHUSUS untuk pengingat tagihan rutin (jika data mengandung key "reminder_type"):
- Jika reminder_type = "h3": kirim pengingat awal yang santai, sebutkan nama tagihan dan estimasi nominal.
  Contoh gaya: "💡 Hei! Biasanya kamu bayar WiFi sekitar Rp200.000 di tanggal 5. Udah disiapkan dananya?"
- Jika reminder_type = "h1": kirim pengingat akhir yang lebih urgen tapi tetap ramah.
  Contoh gaya: "⏰ Besok jatuh tempo WiFi ~Rp200.000. Jangan lupa bayar ya!"

Tipe respons: {$type}
Data:
{$dataJson}

Buat respons natural dalam Bahasa Indonesia. JANGAN gunakan tag markdown tebal atau miring jika tidak diperlukan, gunakan gaya format chat WA biasa.
PROMPT;

        // formatResponse uses free-text output — no schema enforcement
        return $this->callGemini($systemPrompt, 'Formatkan data berikut menjadi respons chat WhatsApp.');
    }

    // ─────────────────────────────────────────────────────
    //  Core API Call Methods
    // ─────────────────────────────────────────────────────

    /**
     * Call Gemini with structured output (JSON mode + response schema).
     * Guarantees valid JSON output matching the provided schema.
     * Used for: classifyIntent, extractTransactions, parseQuery, parseFinancialCommand.
     */
    private function callGeminiStructured(string $systemPrompt, string $userMessage, array $responseSchema): array
    {
        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'contents' => [
                ['parts' => [['text' => $userMessage]]]
            ],
            'generationConfig' => [
                'temperature'      => 0.1,
                'topK'             => 40,
                'topP'             => 0.95,
                'maxOutputTokens'  => 1024,
                'responseMimeType' => 'application/json',
                'responseSchema'   => $responseSchema,
            ],
        ];

        $responseText = $this->executeGeminiRequest($payload);

        return $this->parseJsonResponse($responseText);
    }

    /**
     * Call Gemini for free-text output (no schema enforcement).
     * Used for: formatResponse (natural language WhatsApp messages).
     */
    private function callGemini(string $systemPrompt, string $userMessage): string
    {
        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'contents' => [
                ['parts' => [['text' => $userMessage]]]
            ],
            'generationConfig' => [
                'temperature'     => 0.1,
                'topK'            => 40,
                'topP'            => 0.95,
                'maxOutputTokens' => 1024,
            ],
        ];

        return $this->executeGeminiRequest($payload);
    }

    /**
     * Execute HTTP request to Gemini API with fallback model chain.
     * Shared by both callGemini and callGeminiStructured.
     */
    private function executeGeminiRequest(array $payload): string
    {
        foreach ($this->fallbackModels as $model) {
            $url = "{$this->baseApiUrl}/{$model}:generateContent?key={$this->apiKey}";

            try {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(20)
                    ->post($url, $payload);

                if ($response->successful()) {
                    $candidates = $response->json('candidates', []);
                    if (!empty($candidates)) {
                        $text = $candidates[0]['content']['parts'][0]['text'] ?? '{}';

                        if ($model !== $this->fallbackModels[0]) {
                            Log::info('Gemini fallback used', ['model' => $model]);
                        }

                        return $text;
                    }
                    // Empty candidates — try next model
                    continue;
                }

                $status = $response->status();

                // 429 = rate limit exceeded, 404 = model not available — try next model
                if ($status === 429 || $status === 404) {
                    Log::warning('Gemini model unavailable, trying fallback', [
                        'model'  => $model,
                        'status' => $status,
                    ]);
                    continue;
                }

                // Any other error (400, 500, etc.) — log and abort
                Log::error('Gemini API error', [
                    'model'  => $model,
                    'status' => $status,
                    'body'   => $response->body(),
                ]);
                return '{}';

            } catch (\Exception $e) {
                Log::warning('Gemini model exception, trying fallback', [
                    'model'   => $model,
                    'message' => $e->getMessage(),
                ]);
                continue;
            }
        }

        // All models exhausted
        Log::error('All Gemini fallback models exhausted — no response available.');
        return '{}';
    }

    /**
     * Parse JSON from Gemini response text.
     * With structured output mode, this should already be valid JSON,
     * but we keep the markdown-stripping fallback for robustness.
     */
    private function parseJsonResponse(string $response): array
    {
        // Strip markdown code blocks if present (fallback safety)
        $response = preg_replace('/```json\s*/i', '', $response);
        $response = preg_replace('/```\s*$/i', '', $response);
        $response = trim($response);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse Gemini JSON response', [
                'response' => $response,
                'error' => json_last_error_msg(),
            ]);
            return [];
        }

        return $data;
    }
}
