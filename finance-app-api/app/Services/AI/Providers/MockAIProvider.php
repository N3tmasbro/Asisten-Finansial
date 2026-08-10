<?php

namespace App\Services\AI\Providers;

use App\Contracts\AIProviderInterface;
use App\DTOs\FinancialCommandDTO;
use App\DTOs\IntentClassificationDTO;
use App\DTOs\ParsedTransactionDTO;
use App\DTOs\QueryParametersDTO;
use App\Enums\MessageIntent;
use App\Enums\TransactionType;

/**
 * Mock AI Provider for testing without API calls.
 * Uses simple keyword matching to simulate AI behavior.
 */
class MockAIProvider implements AIProviderInterface
{
    public function classifyIntent(string $message, array $context = []): IntentClassificationDTO
    {
        $message = mb_strtolower($message);

        // Check for delete keywords
        if (preg_match('/(hapus|delete|hilangkan|batalkan\s+(?:transaksi|yang))/i', $message)) {
            return new IntentClassificationDTO(MessageIntent::DeleteTransaction, 0.85);
        }

        // Check for inspect keywords (read-only data viewing)
        if (preg_match('/(daftar\s+(?:wallet|kategori|budget)|saldo|transaksi\s+terakhir|riwayat|history|detail\s+transaksi|budget\s+\w+\s+(?:bulan|minggu))/i', $message)) {
            return new IntentClassificationDTO(MessageIntent::InspectRecords, 0.85);
        }

        // Check for manage keywords (create/rename/update wallet/category/budget)
        if (preg_match('/(buat\s+(?:wallet|kategori|budget)|tambah\s+(?:wallet|kategori|budget)|rename|ganti\s+nama|ubah\s+(?:nama|budget)|naikkan|turunkan)/i', $message)) {
            return new IntentClassificationDTO(MessageIntent::ManageRecords, 0.85);
        }

        // Check for correction keywords
        if (preg_match('/(salah|koreksi|ubah|ganti|bukan|ralat|harusnya)/i', $message)) {
            return new IntentClassificationDTO(MessageIntent::Correction, 0.85);
        }

        // Check for query keywords
        if (preg_match('/(berapa|habis|total|pengeluaran|pemasukan|sisa|ringkasan|summary|trend|prediksi)/i', $message)) {
            return new IntentClassificationDTO(MessageIntent::QueryReport, 0.85);
        }

        // Check for greeting keywords
        if (preg_match('/^(halo|hai|hi|hey|pagi|siang|sore|malam|terima kasih|thanks|makasih|siapa)/i', $message)) {
            return new IntentClassificationDTO(MessageIntent::GreetingSmallTalk, 0.90);
        }

        // Check for transaction keywords (contains number)
        if (preg_match('/\d+/', $message) || preg_match('/(ribu|rb|juta|jt|k)\b/i', $message)) {
            return new IntentClassificationDTO(MessageIntent::AddTransaction, 0.80);
        }

        return new IntentClassificationDTO(MessageIntent::Unclear, 0.50);
    }

    public function extractTransactions(string $message, array $categories, array $context = []): array
    {
        $transactions = [];

        // Split by common separators
        $parts = preg_split('/\b(sama|terus|juga|dan)\b|[,\n]/i', $message);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            $amount = $this->extractAmount($part);
            if ($amount <= 0) {
                continue;
            }

            $type = $this->detectType($part);
            $category = $this->detectCategory($part, $categories);
            $description = $this->extractDescription($part);

            $transactions[] = new ParsedTransactionDTO(
                description: $description,
                amount: $amount,
                type: $type,
                categoryName: $category,
                confidence: 0.75,
            );
        }

        // If no transactions parsed, create a fallback
        if (empty($transactions)) {
            $amount = $this->extractAmount($message);
            $transactions[] = new ParsedTransactionDTO(
                description: mb_substr($message, 0, 100),
                amount: max($amount, 0),
                type: TransactionType::Expense,
                categoryName: 'Lainnya',
                confidence: 0.40,
            );
        }

        return $transactions;
    }

    public function parseQuery(string $message, array $context = []): QueryParametersDTO
    {
        $message = mb_strtolower($message);

        $queryType = 'general_summary';
        $period = 'this_month';
        $categoryFilter = null;

        if (preg_match('/(kategori|breakdown)/i', $message)) {
            $queryType = 'total_by_category';
        } elseif (preg_match('/(trend|naik|turun|dibanding)/i', $message)) {
            $queryType = 'trend_comparison';
        } elseif (preg_match('/(prediksi|habis kapan|sisa.*sampai)/i', $message)) {
            $queryType = 'balance_prediction';
        } elseif (preg_match('/(terbesar|tertinggi|paling banyak|top)/i', $message)) {
            $queryType = 'top_spending';
        } elseif (preg_match('/(total|berapa|habis)/i', $message)) {
            $queryType = 'total_by_period';
        }

        if (preg_match('/(minggu ini|pekan ini)/i', $message)) {
            $period = 'this_week';
        } elseif (preg_match('/(minggu lalu|pekan lalu)/i', $message)) {
            $period = 'last_week';
        } elseif (preg_match('/(bulan lalu)/i', $message)) {
            $period = 'last_month';
        } elseif (preg_match('/(hari ini|today)/i', $message)) {
            $period = 'today';
        }

        // Simple category detection in query
        $categoryKeywords = [
            'makan' => 'Makan & Minum',
            'kopi' => 'Makan & Minum',
            'transport' => 'Transport',
            'bensin' => 'Transport',
            'belanja' => 'Belanja',
            'tagihan' => 'Tagihan',
            'hiburan' => 'Hiburan',
        ];

        foreach ($categoryKeywords as $keyword => $cat) {
            if (str_contains($message, $keyword)) {
                $categoryFilter = $cat;
                break;
            }
        }

        return new QueryParametersDTO(
            queryType: $queryType,
            period: $period,
            categoryFilter: $categoryFilter,
        );
    }

    public function parseFinancialCommand(string $message, array $context = []): FinancialCommandDTO
    {
        $messageLower = mb_strtolower($message);

        // Delete transaction
        if (preg_match('/(hapus|delete|hilangkan)\s+(?:yang\s+|transaksi\s+)?(.+)/i', $messageLower, $matches)) {
            $description = trim($matches[2]);
            return FinancialCommandDTO::fromAIResponse([
                'action' => 'delete_transaction',
                'target' => ['description' => $description],
                'confidence' => 0.8,
            ], $message);
        }

        // Create wallet
        if (preg_match('/(?:buat|tambah)\s+wallet\s+(.+)/i', $messageLower, $matches)) {
            return FinancialCommandDTO::fromAIResponse([
                'action' => 'create_wallet',
                'data' => ['name' => trim($matches[1])],
                'confidence' => 0.9,
            ], $message);
        }

        // Rename wallet
        if (preg_match('/(?:rename|ganti\s+nama)\s+wallet\s+(.+?)\s+(?:jadi|ke|menjadi)\s+(.+)/i', $messageLower, $matches)) {
            return FinancialCommandDTO::fromAIResponse([
                'action' => 'rename_wallet',
                'data' => ['old_name' => trim($matches[1]), 'new_name' => trim($matches[2])],
                'confidence' => 0.9,
            ], $message);
        }

        // Create category
        if (preg_match('/(?:buat|tambah)\s+kategori\s+(.+)/i', $messageLower, $matches)) {
            return FinancialCommandDTO::fromAIResponse([
                'action' => 'create_category',
                'data' => ['name' => trim($matches[1]), 'type' => 'expense'],
                'confidence' => 0.9,
            ], $message);
        }

        // Create/update budget
        if (preg_match('/(?:buat|tambah|set)\s+budget\s+(.+?)\s+(\d[\d.,]*\s*(?:ribu|rb|juta|jt|k)?)/i', $messageLower, $matches)) {
            $amount = $this->extractAmount($matches[2]);
            return FinancialCommandDTO::fromAIResponse([
                'action' => 'create_budget',
                'data' => ['category' => trim($matches[1]), 'amount' => $amount, 'period' => 'monthly'],
                'confidence' => 0.85,
            ], $message);
        }

        // Update budget (naikkan/turunkan)
        if (preg_match('/budget\s+(.+?)\s+(?:naikkan|turunkan|ubah|jadi)\s+(?:jadi\s+)?(\d[\d.,]*\s*(?:ribu|rb|juta|jt|k)?)/i', $messageLower, $matches)) {
            $amount = $this->extractAmount($matches[2]);
            return FinancialCommandDTO::fromAIResponse([
                'action' => 'update_budget',
                'data' => ['category' => trim($matches[1]), 'amount' => $amount],
                'confidence' => 0.85,
            ], $message);
        }

        // Correction: amount change
        if (preg_match('/(?:yang|transaksi)\s+(.+?)\s+(?:tadi\s+)?(?:harusnya|salah.*jadi|seharusnya|ganti\s+jadi)\s+(\d[\d.,]*\s*(?:ribu|rb|juta|jt|k)?)/i', $messageLower, $matches)) {
            $amount = $this->extractAmount($matches[2]);
            return FinancialCommandDTO::fromAIResponse([
                'action' => 'update_transaction',
                'target' => ['description' => trim($matches[1])],
                'changes' => ['amount' => $amount],
                'confidence' => 0.8,
            ], $message);
        }

        // Correction: category change
        if (preg_match('/(?:yang|transaksi)\s+(.+?)\s+(?:tadi\s+)?(?:harusnya|pindah(?:kan)?)\s+(?:kategori\s+)?(.+)/i', $messageLower, $matches)) {
            return FinancialCommandDTO::fromAIResponse([
                'action' => 'update_transaction',
                'target' => ['description' => trim($matches[1])],
                'changes' => ['category' => trim($matches[2])],
                'confidence' => 0.75,
            ], $message);
        }

        // Correction: wallet change
        if (preg_match('/(?:yang|transaksi)\s+(.+?)\s+(?:tadi\s+)?(?:bukan|dari)\s+\w+\s*,?\s*(?:tapi|pakai|pake)\s+(.+)/i', $messageLower, $matches)) {
            return FinancialCommandDTO::fromAIResponse([
                'action' => 'update_transaction',
                'target' => ['description' => trim($matches[1])],
                'changes' => ['wallet' => trim($matches[2])],
                'confidence' => 0.8,
            ], $message);
        }

        // Generic fallback
        return FinancialCommandDTO::fromAIResponse([
            'action' => 'unknown',
            'confidence' => 0.3,
        ], $message);
    }

    public function formatResponse(string $type, array $data, array $context = []): string
    {
        switch ($type) {
            case 'transaction_confirmation':
                return $this->formatTransactionConfirmation($data);
            case 'query_result':
                return $this->formatQueryResult($data);
            case 'correction_confirmation':
                return $this->formatCorrectionConfirmation($data);
            case 'greeting':
                return 'Halo! 👋 Aku asisten keuanganmu. Kirim aja pengeluaran/pemasukan kamu, nanti aku catat otomatis! 💰';
            case 'unclear':
                return 'Hmm, aku kurang paham nih 🤔 Coba kirim ulang ya, misalnya "beli kopi 20rb" atau tanya "bulan ini habis berapa?"';
            default:
                return 'Dicatat ya! ✅';
        }
    }

    private function formatTransactionConfirmation(array $data): string
    {
        $lines = ['Tercatat! ✅'];

        foreach ($data['transactions'] ?? [] as $tx) {
            $amount = number_format($tx['amount'] ?? 0, 0, ',', '.');
            $emoji = ($tx['type'] ?? 'expense') === 'income' ? '💰' : '💸';
            $lines[] = "{$emoji} {$tx['description']} — Rp{$amount} [{$tx['category']}]";
        }

        if (!empty($data['low_confidence'])) {
            $lines[] = "\n⚠️ Ada yang kurang yakin, cek di dashboard atau kirim koreksi ya.";
        }

        return implode("\n", $lines);
    }

    private function formatQueryResult(array $data): string
    {
        $total = number_format($data['total'] ?? 0, 0, ',', '.');
        $period = $data['period_label'] ?? 'bulan ini';

        return "📊 Total pengeluaranmu {$period}: Rp{$total}";
    }

    private function formatCorrectionConfirmation(array $data): string
    {
        return "Sudah dikoreksi ya! ✏️ " . ($data['summary'] ?? '');
    }

    private function extractAmount(string $text): int
    {
        // Match patterns like: 200ribu, 200rb, 200k, 200.000, 1.5jt, 1,5juta, 1500000
        if (preg_match('/(\d+[\.,]?\d*)\s*(juta|jt)/i', $text, $matches)) {
            $num = str_replace(',', '.', $matches[1]);
            return (int) ((float) $num * 1000000);
        }

        if (preg_match('/(\d+[\.,]?\d*)\s*(ribu|rb|k)\b/i', $text, $matches)) {
            $num = str_replace(',', '.', $matches[1]);
            return (int) ((float) $num * 1000);
        }

        if (preg_match('/(\d{1,3}(?:\.\d{3})+)/', $text, $matches)) {
            return (int) str_replace('.', '', $matches[1]);
        }

        if (preg_match('/(\d+)/', $text, $matches)) {
            $num = (int) $matches[1];
            return $num >= 1000 ? $num : 0;
        }

        return 0;
    }

    private function detectType(string $text): TransactionType
    {
        if (preg_match('/(gajian|gaji|dapat|dapet|terima|masuk|transfer masuk)/i', $text)) {
            return TransactionType::Income;
        }

        return TransactionType::Expense;
    }

    private function detectCategory(string $text, array $categories): string
    {
        $text = mb_strtolower($text);

        $keywordMap = [
            'Makan & Minum' => ['makan', 'kopi', 'nasi', 'ayam', 'minum', 'restoran', 'warung', 'snack', 'jajan', 'bakso', 'soto', 'gorengan', 'es', 'teh', 'susu'],
            'Transport' => ['bensin', 'parkir', 'grab', 'gojek', 'ojek', 'taxi', 'tol', 'bus', 'kereta', 'bbm', 'pertamax', 'solar', 'uber', 'angkot'],
            'Belanja' => ['beli', 'belanja', 'shopee', 'tokopedia', 'lazada', 'toko', 'mall', 'baju', 'sepatu', 'tas'],
            'Tagihan' => ['listrik', 'air', 'pln', 'pdam', 'internet', 'wifi', 'pulsa', 'kuota', 'iuran', 'cicilan', 'kredit'],
            'Hiburan' => ['nonton', 'bioskop', 'netflix', 'spotify', 'game', 'main', 'liburan', 'wisata', 'piknik'],
            'Kesehatan' => ['obat', 'dokter', 'rumah sakit', 'rs', 'apotek', 'vitamin', 'klinik'],
            'Pendidikan' => ['buku', 'kursus', 'les', 'sekolah', 'kuliah', 'spp', 'udemy'],
            'Gaji' => ['gaji', 'gajian', 'salary'],
            'Bonus/THR' => ['bonus', 'thr'],
            'Freelance/Sampingan' => ['freelance', 'sampingan', 'proyek', 'project'],
        ];

        foreach ($keywordMap as $category => $keywords) {
            if (in_array($category, $categories)) {
                foreach ($keywords as $keyword) {
                    if (str_contains($text, $keyword)) {
                        return $category;
                    }
                }
            }
        }

        return 'Lainnya';
    }

    private function extractDescription(string $text): string
    {
        // Remove amount patterns to get description
        $desc = preg_replace('/\d+[\.,]?\d*\s*(ribu|rb|juta|jt|k)\b/i', '', $text);
        $desc = preg_replace('/\d{1,3}(?:\.\d{3})+/', '', $desc);
        $desc = preg_replace('/Rp\.?\s*/i', '', $desc);
        $desc = preg_replace('/\s+/', ' ', $desc);
        $desc = trim($desc);

        return $desc ?: mb_substr($text, 0, 50);
    }
}
