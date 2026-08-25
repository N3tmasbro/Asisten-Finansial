<?php

namespace App\Services\Chat;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves candidate transactions from natural-language hints.
 *
 * Used by CorrectionHandler and DeleteHandler to find which transaction
 * the user is referring to. Never relies on AI-generated database IDs.
 */
class TransactionCandidateResolver
{
    /**
     * Find candidate transactions matching the given hints.
     *
     * @param User $user
     * @param array $targetHints Keys: description, amount, category, wallet, date
     * @return Collection<Transaction>
     */
    public function resolve(User $user, ?array $targetHints = null): Collection
    {
        $query = Transaction::where('user_id', $user->id)
            ->with(['category:id,name,icon', 'wallet:id,name']);

        $hasHints = false;

        if ($targetHints) {
            // Description hint — fuzzy match
            if (!empty($targetHints['description'])) {
                $desc = $targetHints['description'];
                $query->where(function ($q) use ($desc) {
                    $q->where('description', 'LIKE', '%' . $desc . '%')
                      ->orWhere('raw_input', 'LIKE', '%' . $desc . '%');
                });
                $hasHints = true;
            }

            // Amount hint — exact match
            if (!empty($targetHints['amount'])) {
                $amount = (int) $targetHints['amount'];
                // Allow ±10% tolerance for amount matching
                $tolerance = max((int) ($amount * 0.1), 1000);
                $query->whereBetween('amount', [$amount - $tolerance, $amount + $tolerance]);
                $hasHints = true;
            }

            // Category hint — match by category name
            if (!empty($targetHints['category'])) {
                $categoryName = $targetHints['category'];
                $categoryIds = Category::forUser($user->id)
                    ->where('name', 'LIKE', '%' . $categoryName . '%')
                    ->pluck('id');

                if ($categoryIds->isNotEmpty()) {
                    $query->whereIn('category_id', $categoryIds);
                    $hasHints = true;
                }
            }

            // Wallet hint — match by wallet name
            if (!empty($targetHints['wallet'])) {
                $walletName = $targetHints['wallet'];
                $walletIds = $user->wallets()
                    ->where('name', 'LIKE', '%' . $walletName . '%')
                    ->pluck('id');

                if ($walletIds->isNotEmpty()) {
                    $query->whereIn('wallet_id', $walletIds);
                    $hasHints = true;
                }
            }

            // Date hint — exact date or ±1 day range
            if (!empty($targetHints['date'])) {
                try {
                    $date = Carbon::parse($targetHints['date']);
                    $query->whereBetween('transaction_date', [
                        $date->copy()->subDay()->toDateString(),
                        $date->copy()->addDay()->toDateString(),
                    ]);
                    $hasHints = true;
                } catch (\Exception $e) {
                    // Invalid date format, skip this hint
                }
            }
        }

        return $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * Format candidates as a numbered list for user display matching the mobile template.
     *
     * @return array{text: string, candidates: array} Text for display + candidate data for storage
     */
    public function formatCandidateList(Collection $candidates, string $action = 'koreksi'): array
    {
        $actionVerb   = $action === 'hapus' ? 'dihapus' : 'diubah';
        $numberEmojis = ['1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣', '🔟'];

        $months = [
            'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr',
            'May' => 'Mei', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Agu',
            'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des',
        ];

        $lines = ["📋 *Pilih transaksi yang ingin {$actionVerb}:*\n"];
        $candidateData = [];
        $displayCount = min($candidates->count(), 10);

        foreach ($candidates->take(10) as $i => $tx) {
            $amount   = number_format($tx->amount, 0, ',', '.');
            $isIncome = $tx->type->value === 'income';
            $sign     = $isIncome ? '➕' : '➖';
            $wallet   = $tx->wallet->name ?? 'Cash';
            $category = $tx->category->name ?? 'Lainnya';
            $monthEng = $tx->transaction_date->format('M');
            $dateStr  = $tx->transaction_date->format('j') . ' ' . ($months[$monthEng] ?? $monthEng) . ' ' . $tx->transaction_date->format('Y');

            $emojiNum = $numberEmojis[$i] ?? ($i + 1) . '.';

            $lines[] = "{$emojiNum} *{$tx->description}*";
            $lines[] = "   {$sign}Rp{$amount} • {$wallet} • {$dateStr}";
            $lines[] = "   🏷️ {$category}";
            $lines[] = "";

            $candidateData[] = [
                'id'          => $tx->id,
                'description' => $tx->description,
                'amount'      => $tx->amount,
                'category'    => $category,
                'wallet'      => $wallet,
            ];
        }

        $lines[] = "Balas dengan *nomor* (1 - {$displayCount}) atau deskripsi yang lebih spesifik.";

        if ($action === 'hapus') {
            $lines[] = "";
            $lines[] = "〰️〰️〰️";
            $lines[] = "💡 *Opsi hapus:*";
            $lines[] = "  • Balas *nomor* (misal: *1*) untuk hapus 1 transaksi";
            $lines[] = "  • Ketik _hapus semua transaksi_ untuk menghapus seluruh riwayat";
        }

        return [
            'text'       => implode("\n", $lines),
            'candidates' => $candidateData,
        ];
    }
}
