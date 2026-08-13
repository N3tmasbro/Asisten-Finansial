<?php

namespace App\Services\Analytics;

use App\Models\RecurringPattern;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Detects recurring expense patterns from a user's transaction history.
 *
 * Algorithm:
 * 1. Fetch all expense transactions in the past 90 days.
 * 2. Normalize descriptions (lowercase, trim punctuation).
 * 3. Group by normalized description + amount within ±10% tolerance.
 * 4. Any group with ≥2 occurrences across different calendar months,
 *    where the day-of-month is within ±3 days each time, is considered recurring.
 * 5. Save/update entries in recurring_patterns table.
 */
class RecurringPatternDetectorService
{
    private const LOOKBACK_DAYS = 90;
    private const AMOUNT_TOLERANCE_PERCENT = 0.10; // ±10%
    private const MIN_OCCURRENCES = 2;
    private const DAY_TOLERANCE = 3; // ±3 days

    /**
     * Run detection for a single user and upsert recurring_patterns.
     */
    public function detectForUser(int $userId): int
    {
        $since = Carbon::now()->subDays(self::LOOKBACK_DAYS)->toDateString();
        $today = Carbon::now()->toDateString();

        // Fetch all expense transactions in lookback window
        $transactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$since, $today])
            ->orderBy('transaction_date')
            ->get(['id', 'description', 'amount', 'category_id', 'transaction_date']);

        if ($transactions->isEmpty()) {
            return 0;
        }

        // Group by normalized description fingerprint
        $groups = $this->groupByFingerprint($transactions);

        $detected = 0;

        foreach ($groups as $fingerprint => $group) {
            if ($group->count() < self::MIN_OCCURRENCES) {
                continue;
            }

            // Ensure occurrences span multiple calendar months
            $months = $group->map(fn ($tx) => Carbon::parse($tx->transaction_date)->format('Y-m'))
                ->unique();

            if ($months->count() < self::MIN_OCCURRENCES) {
                continue;
            }

            // Check if day-of-month is consistent (within ±DAY_TOLERANCE)
            $days = $group->map(fn ($tx) => Carbon::parse($tx->transaction_date)->day);
            $avgDay = (int) round($days->average());
            $maxDeviation = $days->max(fn ($d) => abs($d - $avgDay));

            if ($maxDeviation > self::DAY_TOLERANCE) {
                continue; // Days too spread out — not recurring on a consistent date
            }

            // Compute average amount and tolerance band
            $avgAmount = (int) round($group->average('amount'));
            $tolerance = (int) round($avgAmount * self::AMOUNT_TOLERANCE_PERCENT);

            // Most common category
            $categoryId = $group->groupBy('category_id')
                ->sortByDesc(fn ($g) => $g->count())
                ->keys()
                ->first();

            // Next expected date: avgDay of the next month from last occurrence
            $lastSeen = Carbon::parse($group->max('transaction_date'));
            $nextExpected = Carbon::now()->day($avgDay);
            if ($nextExpected->lte(Carbon::now())) {
                $nextExpected->addMonth();
            }

            RecurringPattern::updateOrCreate(
                [
                    'user_id' => $userId,
                    'description_pattern' => $fingerprint,
                ],
                [
                    'category_id' => $categoryId,
                    'amount_avg' => $avgAmount,
                    'amount_tolerance' => $tolerance,
                    'expected_day_of_month' => $avgDay,
                    'day_tolerance' => self::DAY_TOLERANCE,
                    'occurrences_count' => $group->count(),
                    'last_seen_date' => $lastSeen->toDateString(),
                    'next_expected_date' => $nextExpected->toDateString(),
                    'is_active' => true,
                ]
            );

            $detected++;
        }

        return $detected;
    }

    /**
     * Normalize a transaction description to a simple fingerprint key.
     * E.g., "Bayar Wifi Indihome" → "bayar wifi indihome"
     */
    private function normalizeDescription(string $description): string
    {
        $cleaned = strtolower(trim($description));
        $cleaned = preg_replace('/[^a-z0-9\s]/u', '', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        return $cleaned;
    }

    /**
     * Group transactions by (normalized_description + amount_bucket) fingerprint.
     * Amount bucket: rounded to nearest 10% band to allow slight variation.
     */
    private function groupByFingerprint(Collection $transactions): Collection
    {
        return $transactions->groupBy(function ($tx) {
            $description = $this->normalizeDescription($tx->description);
            // Round amount to nearest 10% bucket for fuzzy grouping
            $amountBucket = (int) round($tx->amount / ($tx->amount * self::AMOUNT_TOLERANCE_PERCENT + 1)) * 10;
            return $description; // Group only by description — filter by amount tolerance after
        })->filter(function ($group) {
            // Within each description group, further ensure amounts are within ±10% of each other
            if ($group->count() < 2) return false;
            $amounts = $group->pluck('amount');
            $avg = $amounts->average();
            return $amounts->every(fn ($a) => abs($a - $avg) / $avg <= self::AMOUNT_TOLERANCE_PERCENT * 2);
        });
    }
}
