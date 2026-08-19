<?php

namespace App\Services\AI\Providers;

use App\Contracts\AIProviderInterface;
use App\DTOs\FinancialCommandDTO;
use App\DTOs\IntentClassificationDTO;
use App\DTOs\ParsedTransactionDTO;
use App\DTOs\QueryParametersDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', '');
        $this->model = config('services.anthropic.model', 'claude-sonnet-4-20250514');
    }

    public function classifyIntent(string $message, array $context = []): IntentClassificationDTO
    {
        $systemPrompt = <<<PROMPT
Kamu adalah classifier pesan untuk aplikasi pencatat keuangan. Tugasmu HANYA menentukan intent dari pesan user.

Intent yang tersedia:
- "add_transaction": user ingin mencatat pengeluaran atau pemasukan (contoh: "isi bensin 200 ribu", "beli kopi 20rb", "gajian 5 juta")
- "query_report": user ingin bertanya tentang keuangannya (contoh: "bulan ini habis berapa?", "pengeluaran makan minggu ini", "ringkasan bulan ini")
- "correction": user ingin mengoreksi/mengubah transaksi yang sudah dicatat (contoh: "eh yang tadi salah", "koreksi yang bensin jadi 250rb", "yang kopi tadi harusnya Hiburan")
- "delete_transaction": user ingin menghapus transaksi (contoh: "hapus yang bensin tadi", "delete transaksi terakhir", "batalkan yang kopi")
- "inspect_records": user ingin melihat data tanpa mengubah (contoh: "transaksi terakhir apa?", "saldo BCA berapa?", "daftar wallet", "budget makan bulan ini?")
- "manage_records": user ingin membuat/mengubah wallet, kategori, atau budget (contoh: "buat wallet Dana", "tambah kategori Investasi", "buat budget makan 2 juta")
- "greeting_smalltalk": sapaan atau obrolan ringan (contoh: "halo", "terima kasih", "siapa kamu?")
- "unclear": pesan tidak jelas atau tidak terkait keuangan

Balas HANYA dalam format JSON:
{"intent": "...", "confidence": 0.0-1.0}

Jangan tambahkan penjelasan apapun di luar JSON.
PROMPT;

        $response = $this->callClaude($systemPrompt, $message);
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
8. Jika ada detail tambahan (tempat, alasan, catatan), masukkan ke field "notes".
   Contoh: "beli kopi 20rb di Starbucks bareng Budi" → notes = "di Starbucks bareng Budi"
9. Set needs_clarification = true jika nominal tidak disebutkan, kategori sangat tidak jelas, atau pesan ambigu.
   Isi clarification_reason dengan alasan singkat jika needs_clarification = true.

Balas HANYA dalam format JSON array:
[{"description": "...", "amount": 20000, "type": "expense", "category": "Makan & Minum", "wallet": null, "date": null, "notes": null, "confidence": 0.95, "needs_clarification": false, "clarification_reason": null}]

Jangan tambahkan penjelasan apapun di luar JSON.
PROMPT;

        $response = $this->callClaude($systemPrompt, $message);
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

        $response = $this->callClaude($systemPrompt, $message);
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

ACTIONS: update_transaction, delete_transaction, create_wallet, rename_wallet, create_category, rename_category, create_budget, update_budget, delete_budget

FORMAT OUTPUT (JSON ketat):
{"action":"...","target":{"description":null,"amount":null,"category":null,"wallet":null,"date":null},"changes":{"amount":null,"category":null,"wallet":null,"description":null,"date":null,"type":null},"data":{"name":null,"type":null,"amount":null,"category":null,"period":null,"old_name":null,"new_name":null},"confidence":0.0}

Isi HANYA field yang relevan. Sisanya null. Jangan tambahkan penjelasan apapun di luar JSON.
PROMPT;

        // Build context string
        $contextParts = ['[CONTEXT]'];

        if (!empty($context['recent_transactions'])) {
            $contextParts[] = 'Transaksi terbaru:';
            foreach ($context['recent_transactions'] as $i => $tx) {
                $amount = number_format($tx['amount'] ?? 0, 0, ',', '.');
                $contextParts[] = ($i + 1) . ". {$tx['description']} — Rp{$amount} — {$tx['category']} — {$tx['wallet']} — {$tx['date']}";
            }
        }

        if (!empty($context['wallets'])) {
            $contextParts[] = 'Wallet: ' . implode(', ', $context['wallets']);
        }

        if (!empty($context['categories_expense'])) {
            $contextParts[] = 'Kategori expense: ' . implode(', ', $context['categories_expense']);
        }

        $contextParts[] = '';
        $contextParts[] = '[PESAN USER]';
        $contextParts[] = $message;

        $userMessage = implode("\n", $contextParts);
        $response = $this->callClaude($systemPrompt, $userMessage);
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

Buat respons natural dalam Bahasa Indonesia.
PROMPT;

        return $this->callClaude($systemPrompt, 'Formatkan data berikut menjadi respons chat WhatsApp.');
    }

    private function callClaude(string $systemPrompt, string $userMessage): string
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post($this->baseUrl, [
                'model' => $this->model,
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

            if ($response->successful()) {
                return $response->json('content.0.text', '{}');
            }

            Log::error('Claude API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return '{}';
        } catch (\Exception $e) {
            Log::error('Claude API exception', ['message' => $e->getMessage()]);
            return '{}';
        }
    }

    private function parseJsonResponse(string $response): array
    {
        // Strip markdown code blocks if present
        $response = preg_replace('/```json\s*/i', '', $response);
        $response = preg_replace('/```\s*$/i', '', $response);
        $response = trim($response);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse Claude JSON response', [
                'response' => $response,
                'error' => json_last_error_msg(),
            ]);
            return [];
        }

        return $data;
    }
}
