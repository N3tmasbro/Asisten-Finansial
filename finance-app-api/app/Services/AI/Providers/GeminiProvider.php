<?php

namespace App\Services\AI\Providers;

use App\Contracts\AIProviderInterface;
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
- "add_transaction": user ingin mencatat pengeluaran atau pemasukan (contoh: "isi bensin 200 ribu", "beli kopi 20rb", "gajian 5 juta")
- "query_report": user ingin bertanya tentang keuangannya (contoh: "bulan ini habis berapa?", "pengeluaran makan minggu ini")
- "correction": user ingin mengoreksi transaksi yang sudah dicatat (contoh: "eh yang tadi salah", "koreksi yang bensin jadi 250rb")
- "greeting_smalltalk": sapaan atau obrolan ringan (contoh: "halo", "terima kasih", "siapa kamu?")
- "unclear": pesan tidak jelas atau tidak terkait keuangan

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
