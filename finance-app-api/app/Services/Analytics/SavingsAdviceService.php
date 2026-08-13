<?php

namespace App\Services\Analytics;

use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Generates personalized savings advice based on the user's spending patterns.
 *
 * Algorithm:
 * 1. Get top 3 expense categories by total spend this month.
 * 2. Get the same categories' spend from last month for comparison.
 * 3. For each category, calculate:
 *    - Monthly spend this month
 *    - Change vs last month (absolute + percentage)
 *    - Potential savings if reduced by 20%
 * 4. Return a structured advice payload for the AI formatter.
 */
class SavingsAdviceService
{
    private const TOP_CATEGORIES = 3;
    private const SAVINGS_REDUCTION_RATE = 0.20; // Suggest cutting 20%

    /**
     * Generate savings advice for a user.
     */
    public function generateAdvice(int $userId): array
    {
        $now = Carbon::now();
        $thisMonthFrom = $now->copy()->startOfMonth()->toDateString();
        $thisMonthTo   = $now->copy()->endOfMonth()->toDateString();
        $lastMonthFrom = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $lastMonthTo   = $now->copy()->subMonth()->endOfMonth()->toDateString();

        // This month's spending by category
        $thisMonth = $this->getByCategory($userId, $thisMonthFrom, $thisMonthTo);

        if ($thisMonth->isEmpty()) {
            return [
                'has_data' => false,
                'message'  => 'no_transactions',
            ];
        }

        // Last month's spending by category (for comparison)
        $lastMonth = $this->getByCategory($userId, $lastMonthFrom, $lastMonthTo);
        $lastMonthMap = $lastMonth->keyBy('category_name');

        // Total expense this month
        $totalThisMonth = $thisMonth->sum('total');

        // Build top 3 suggestions
        $suggestions = $thisMonth
            ->take(self::TOP_CATEGORIES)
            ->map(function ($item) use ($lastMonthMap, $totalThisMonth) {
                $lastMonthTotal = $lastMonthMap->get($item->category_name)?->total ?? 0;
                $changeAmount   = $item->total - $lastMonthTotal;
                $changePercent  = $lastMonthTotal > 0
                    ? round(($changeAmount / $lastMonthTotal) * 100, 1)
                    : null;

                $potentialSaving = (int) round($item->total * self::SAVINGS_REDUCTION_RATE);

                return [
                    'category'         => $item->category_name,
                    'icon'             => $item->category_icon ?? '📦',
                    'this_month'       => (int) $item->total,
                    'last_month'       => (int) $lastMonthTotal,
                    'change_amount'    => (int) $changeAmount,
                    'change_percent'   => $changePercent,
                    'share_of_total'   => $totalThisMonth > 0 ? round($item->total / $totalThisMonth * 100, 1) : 0,
                    'potential_saving' => $potentialSaving,
                ];
            })
            ->values()
            ->toArray();

        return [
            'has_data'         => true,
            'total_expense'    => (int) $totalThisMonth,
            'month_label'      => $now->translatedFormat('F Y'),
            'suggestions'      => $suggestions,
            'reduction_rate'   => (int) (self::SAVINGS_REDUCTION_RATE * 100), // e.g. 20
            'total_potential_saving' => array_sum(array_column($suggestions, 'potential_saving')),
        ];
    }

    /**
     * Get expense totals grouped by category for a period, sorted by total descending.
     */
    private function getByCategory(int $userId, string $from, string $to): Collection
    {
        return Transaction::with('category')
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$from, $to])
            ->get(['amount', 'category_id'])
            ->groupBy('category_id')
            ->map(function ($group) {
                $first = $group->first();
                return (object) [
                    'category_name' => $first->category?->name ?? 'Lainnya',
                    'category_icon' => $first->category?->icon ?? '📦',
                    'total'         => $group->sum('amount'),
                    'count'         => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }
}
