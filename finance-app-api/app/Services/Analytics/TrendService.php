<?php

namespace App\Services\Analytics;

use App\Repositories\TransactionRepository;
use Illuminate\Support\Carbon;

class TrendService
{
    public function __construct(
        private TransactionRepository $transactionRepo,
        private SummaryService $summaryService,
    ) {}

    /**
     * Compare two periods (e.g., this month vs last month).
     */
    public function comparePeriods(int $userId, string $currentPeriod = 'this_month', string $previousPeriod = 'last_month'): array
    {
        [$currentFrom, $currentTo] = $this->summaryService->resolvePeriod($currentPeriod);
        [$prevFrom, $prevTo] = $this->summaryService->resolvePeriod($previousPeriod);

        $currentExpense = $this->transactionRepo->totalForPeriod($userId, $currentFrom, $currentTo, 'expense');
        $previousExpense = $this->transactionRepo->totalForPeriod($userId, $prevFrom, $prevTo, 'expense');

        $currentIncome = $this->transactionRepo->totalForPeriod($userId, $currentFrom, $currentTo, 'income');
        $previousIncome = $this->transactionRepo->totalForPeriod($userId, $prevFrom, $prevTo, 'income');

        return [
            'current_period' => $currentPeriod,
            'previous_period' => $previousPeriod,
            'expense' => [
                'current' => $currentExpense,
                'previous' => $previousExpense,
                'change' => $currentExpense - $previousExpense,
                'change_percent' => $previousExpense > 0
                    ? round(($currentExpense - $previousExpense) / $previousExpense * 100, 1)
                    : 0,
            ],
            'income' => [
                'current' => $currentIncome,
                'previous' => $previousIncome,
                'change' => $currentIncome - $previousIncome,
                'change_percent' => $previousIncome > 0
                    ? round(($currentIncome - $previousIncome) / $previousIncome * 100, 1)
                    : 0,
            ],
        ];
    }

    /**
     * Get daily spending trend for a period.
     */
    public function dailyTrend(int $userId, string $period = 'this_month'): array
    {
        [$from, $to] = $this->summaryService->resolvePeriod($period);

        $dailyData = $this->transactionRepo->totalByDay($userId, $from, $to);

        // Build a complete day-by-day series (including zero days)
        $series = [];
        $current = Carbon::parse($from);
        $end = Carbon::parse($to);

        while ($current->lte($end)) {
            $dateStr = $current->toDateString();
            
            // Safe filter because transaction_date might be casted to Carbon instance
            $dayData = $dailyData->filter(function ($item) use ($dateStr) {
                $itemDate = $item->transaction_date;
                $itemDateStr = $itemDate instanceof \Carbon\Carbon 
                    ? $itemDate->toDateString() 
                    : (string) $itemDate;
                return substr($itemDateStr, 0, 10) === $dateStr;
            });

            $series[] = [
                'date' => $dateStr,
                'day_label' => $current->translatedFormat('D, d M'),
                'expense' => (int) $dayData->where('type.value', 'expense')->sum('total'),
                'income' => (int) $dayData->where('type.value', 'income')->sum('total'),
            ];

            $current->addDay();
        }

        return [
            'period' => $period,
            'series' => $series,
        ];
    }

    /**
     * Compare spending per category between two periods.
     */
    public function categoryTrend(int $userId, string $currentPeriod = 'this_month', string $previousPeriod = 'last_month'): array
    {
        [$currentFrom, $currentTo] = $this->summaryService->resolvePeriod($currentPeriod);
        [$prevFrom, $prevTo] = $this->summaryService->resolvePeriod($previousPeriod);

        $currentCategories = $this->transactionRepo->totalByCategory($userId, $currentFrom, $currentTo, 'expense');
        $prevCategories = $this->transactionRepo->totalByCategory($userId, $prevFrom, $prevTo, 'expense');

        $comparison = [];
        foreach ($currentCategories as $current) {
            $prev = $prevCategories->where('category_id', $current->category_id)->first();
            $prevTotal = $prev ? $prev->total : 0;

            $comparison[] = [
                'category_id' => $current->category_id,
                'category_name' => $current->category->name ?? 'Unknown',
                'category_icon' => $current->category->icon ?? '📦',
                'current' => $current->total,
                'previous' => $prevTotal,
                'change' => $current->total - $prevTotal,
                'change_percent' => $prevTotal > 0
                    ? round(($current->total - $prevTotal) / $prevTotal * 100, 1)
                    : ($current->total > 0 ? 100 : 0),
            ];
        }

        return [
            'current_period' => $currentPeriod,
            'previous_period' => $previousPeriod,
            'categories' => $comparison,
        ];
    }
}
