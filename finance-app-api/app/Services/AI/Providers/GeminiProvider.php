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

    public function classifyIntent(string $message, array $context = []): IntentClassificationDTO
    {
        $systemPrompt = <<<PROMPT
Kamu adalah classifier pesan untuk aplikasi pencatat keuangan. Tugasmu HANYA menentukan intent dari pesan user.

Intent yang tersedia:
- "add_transaction": user ingin mencatat transaksi BARU yang belum ada di sistem (contoh: "isi bensin 200 ribu", "beli kopi 20rb", "gajian 5 juta", "makan siang 35rb")
- "query_report": user ingin laporan atau statistik keuangan (contoh: "bulan ini habis berapa?", "pengeluaran makan minggu ini", "ringkasan bulan ini", "total pengeluaran")
- "correction": user ingin MENGUBAH transaksi yang SUDAH ADA di sistem — ditandai kata: "yang tadi", "yang kemarin", "harusnya", "salah", "koreksi", "ubah", "ganti", "ralat", "bukan" (contoh: "yang kopi tadi harusnya Hiburan", "yang bensin salah harusnya 80rb", "koreksi yang tadi jadi 50rb", "bukan BCA tapi Cash")
- "delete_transaction": user ingin MENGHAPUS transaksi yang sudah ada (contoh: "hapus yang bensin tadi", "delete transaksi terakhir", "batalkan yang kopi", "hilangkan transaksi bensin")
- "inspect_records": user ingin MELIHAT data yang ada tanpa mengubah apapun (contoh: "saldo BCA berapa?", "saldo semua wallet", "transaksi terakhir apa?", "daftar wallet", "daftar kategori", "budget makan bulan ini?", "riwayat transaksi", "cek saldo")
- "manage_records": user ingin MEMBUAT atau MENGUBAH wallet/kategori/budget (contoh: "buat wallet Dana", "tambah kategori Investasi", "buat budget makan 2 juta", "rename wallet BCA jadi BCA Digital", "naikkan budget makan jadi 2,5 juta")
- "greeting_smalltalk": sapaan atau obrolan ringan (contoh: "halo", "terima kasih", "siapa kamu?")
- "unclear": pesan tidak jelas atau tidak terkait keuangan

ATURAN PENTING — baca ini dengan seksama:
1. Jika pesan mengandung "yang tadi", "yang kemarin", "harusnya", "salah" = CORRECTION bukan add_transaction
2. Jika pesan mengandung "saldo", "cek", "daftar", "riwayat", "transaksi terakhir" = INSPECT bukan yang lain
3. Jika pesan mengandung "buat", "tambah", "rename", "naikkan", "turunkan" untuk wallet/kategori/budget = MANAGE
4. Jika pesan mengandung "hapus", "delete", "hilangkan" untuk transaksi = DELETE
5. add_transaction HANYA untuk transaksi yang benar-benar BARU, bukan referensi ke transaksi lama

CONTOH NEGATIF (jangan salah klasifikasi):
- "yang kopi tadi harusnya Hiburan" → BUKAN add_transaction, ini CORRECTION
- "yang bensin salah, harusnya 80rb" → BUKAN add_transaction, ini CORRECTION  
- "saldo semua wallet" → BUKAN query_report, ini INSPECT
- "daftar wallet" → BUKAN unclear, ini INSPECT
- "hapus yang bensin tadi" → BUKAN correction, ini DELETE

Balas HANYA dalam format JSON:
{"intent": "...", "confidence": 0.0-1.0}

Jangan tambahkan penjelasan apapun di luar JSON.
PROMPT;

        $response = $this->callGemini($systemPrompt, $message);
        $data = $this->parseJsonResponse($response);

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

Balas HANYA dalam format JSON array:
[{"description": "...", "amount": 20000, "type": "expense", "category": "Makan & Minum", "confidence": 0.95}]

Jangan tambahkan penjelasan apapun di luar JSON.
PROMPT;

        $response = $this->callGemini($systemPrompt, $message);
        $data = $this->parseJsonResponse($response);

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

Balas HANYA dalam format JSON:
{"query_type": "...", "period": "this_month|last_month|this_week|last_week|today|custom", "category_filter": null|"Makan & Minum", "date_from": "YYYY-MM-DD"|null, "date_to": "YYYY-MM-DD"|null}

Jangan tambahkan penjelasan apapun di luar JSON.
PROMPT;

        $response = $this->callGemini($systemPrompt, $message);
        $data = $this->parseJsonResponse($response);

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
4. Konversi angka: "20rb"/"20ribu"/"20k" = 20000, "1,5jt"/"1.5juta" = 1500000
5. Jika user tidak menyebut tanggal, jangan isi date
6. Jika user tidak menyebut wallet, jangan isi wallet
7. Confidence: 0.0-1.0 berdasar kejelasan pesan user

ACTIONS yang tersedia:
- update_transaction: koreksi transaksi yang sudah ada
- delete_transaction: hapus transaksi
- create_wallet: buat wallet baru
- rename_wallet: ubah nama wallet
- create_category: buat kategori baru
- rename_category: ubah nama kategori
- create_budget: buat budget baru
- update_budget: ubah jumlah budget
- delete_budget: hapus budget

FORMAT OUTPUT (JSON ketat):
{"action":"...","target":{"description":null,"amount":null,"category":null,"wallet":null,"date":null},"changes":{"amount":null,"category":null,"wallet":null,"description":null,"date":null,"type":null},"data":{"name":null,"type":null,"amount":null,"category":null,"period":null,"old_name":null,"new_name":null},"confidence":0.0}

Isi HANYA field yang relevan. Sisanya null.

CONTOH:

User: "yang kopi tadi harusnya 75 ribu"
{"action":"update_transaction","target":{"description":"kopi"},"changes":{"amount":75000},"data":null,"confidence":0.9}

User: "yang bensin tadi bukan Cash, tapi BCA"
{"action":"update_transaction","target":{"description":"bensin"},"changes":{"wallet":"BCA"},"data":null,"confidence":0.9}

User: "hapus transaksi makan tadi"
{"action":"delete_transaction","target":{"description":"makan"},"changes":null,"data":null,"confidence":0.85}

User: "buat wallet Dana"
{"action":"create_wallet","target":null,"changes":null,"data":{"name":"Dana"},"confidence":0.95}

User: "rename wallet BCA jadi BCA Digital"
{"action":"rename_wallet","target":null,"changes":null,"data":{"old_name":"BCA","new_name":"BCA Digital"},"confidence":0.9}

User: "buat budget makan 2 juta bulan ini"
{"action":"create_budget","target":null,"changes":null,"data":{"category":"Makan & Minum","amount":2000000,"period":"monthly"},"confidence":0.9}

User: "budget makan naikkan jadi 2,5 juta"
{"action":"update_budget","target":null,"changes":null,"data":{"category":"Makan & Minum","amount":2500000},"confidence":0.85}

User: "buat kategori Investasi"
{"action":"create_category","target":null,"changes":null,"data":{"name":"Investasi","type":"expense"},"confidence":0.95}

User: "yang tadi salah harusnya pemasukan bukan pengeluaran"
{"action":"update_transaction","target":{"description":null},"changes":{"type":"income"},"data":null,"confidence":0.7}

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

Tipe respons: {$type}
Data:
{$dataJson}

Buat respons natural dalam Bahasa Indonesia. JANGAN gunakan tag markdown tebal atau miring jika tidak diperlukan, gunakan gaya format chat WA biasa.
PROMPT;

        return $this->callGemini($systemPrompt, 'Formatkan data berikut menjadi respons chat WhatsApp.');
    }

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

    private function parseJsonResponse(string $response): array
    {
        // Strip markdown code blocks if present
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
