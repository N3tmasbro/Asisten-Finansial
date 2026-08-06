<?php

namespace App\Services\AI\Providers;

use App\Contracts\AIProviderInterface;
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
- "query_report": user ingin bertanya tentang keuangannya (contoh: "bulan ini habis berapa?", "pengeluaran makan minggu ini")
- "correction": user ingin mengoreksi transaksi yang sudah dicatat (contoh: "eh yang tadi salah", "koreksi yang bensin jadi 250rb")
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

Balas HANYA dalam format JSON array:
[{"description": "...", "amount": 20000, "type": "expense", "category": "Makan & Minum", "confidence": 0.95}]

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
