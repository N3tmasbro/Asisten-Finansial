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

        // If no specific hints, limit to recent transactions (last 7 days)
        if (!$hasHints) {
            $query->where('transaction_date', '>=', now()->subDays(7)->toDateString());
        }

        return $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * Format candidates as a numbered list for user display.
     *
     * @return array{text: string, candidates: array} Text for display + candidate data for storage
     */
    public function formatCandidateList(Collection $candidates, string $action = 'koreksi'): array
    {
        $lines = ["Aku menemukan beberapa transaksi yang cocok:\n"];
        $candidateData = [];

        foreach ($candidates->take(5) as $i => $tx) {
            $amount = number_format($tx->amount, 0, ',', '.');
            $type = $tx->type->value === 'income' ? '+' : '-';
            $date = $tx->transaction_date->format('j M Y');
            $wallet = $tx->wallet->name ?? 'Unknown';
            $category = $tx->category->name ?? 'Unknown';

            $lines[] = ($i + 1) . ". {$tx->description} — {$type}Rp{$amount} — {$category} — {$wallet} — {$date}";
            $candidateData[] = [
                'id' => $tx->id,
                'description' => $tx->description,
                'amount' => $tx->amount,
                'category' => $category,
                'wallet' => $wallet,
            ];
        }

        $lines[] = "\nYang mana? Balas nomor atau jelaskan lebih detail.";

        return [
            'text' => implode("\n", $lines),
            'candidates' => $candidateData,
        ];
    }
}
