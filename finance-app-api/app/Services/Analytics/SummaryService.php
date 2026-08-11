<?php

namespace App\Services\Analytics;

use App\Repositories\TransactionRepository;
use Illuminate\Support\Carbon;

class SummaryService
{
    public function __construct(
        private TransactionRepository $transactionRepo,
    ) {}

    /**
     * Get spending summary for a period.
     */
    public function getSummary(int $userId, string $period = 'this_month'): array
    {
        [$from, $to] = $this->resolvePeriod($period);

        $totalExpense = $this->transactionRepo->totalForPeriod($userId, $from, $to, 'expense');
        $totalIncome = $this->transactionRepo->totalForPeriod($userId, $from, $to, 'income');
        $byCategory = $this->transactionRepo->totalByCategory($userId, $from, $to, 'expense');
        $dailyAverage = $this->transactionRepo->dailyAverage($userId, $from, $to);

        return [
            'period' => $period,
            'period_label' => $this->getPeriodLabel($period),
            'date_from' => $from,
            'date_to' => $to,
            'total_expense' => $totalExpense,
            'total_income' => $totalIncome,
            'net' => $totalIncome - $totalExpense,
            'daily_average' => (int) $dailyAverage,
            'by_category' => $byCategory->map(fn($item) => [
                'category_id' => $item->category_id,
                'category_name' => $item->category->name ?? 'Unknown',
                'category_icon' => $item->category->icon ?? '📦',
                'total' => $item->total,
                'count' => $item->count,
                'percentage' => $totalExpense > 0 ? round($item->total / $totalExpense * 100, 1) : 0,
            ])->toArray(),
        ];
    }

    /**
     * Get category breakdown with detailed stats.
     */
    public function getCategoryBreakdown(int $userId, string $period = 'this_month', string $type = 'expense'): array
    {
        [$from, $to] = $this->resolvePeriod($period);

        $categories = $this->transactionRepo->totalByCategory($userId, $from, $to, $type);
        $total = $categories->sum('total');

        return [
            'period' => $period,
            'type' => $type,
            'total' => $total,
            'categories' => $categories->map(fn($item) => [
                'category_id' => $item->category_id,
                'category_name' => $item->category->name ?? 'Unknown',
                'category_icon' => $item->category->icon ?? '📦',
                'total' => $item->total,
                'count' => $item->count,
                'percentage' => $total > 0 ? round($item->total / $total * 100, 1) : 0,
            ])->toArray(),
        ];
    }

    /**
     * Resolve period string to date range.
     */
    public function resolvePeriod(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [$now->toDateString(), $now->toDateString()],
            'this_week' => [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek()->toDateString(), $now->copy()->subWeek()->endOfWeek()->toDateString()],
            'this_month' => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth()->toDateString(), $now->copy()->subMonth()->endOfMonth()->toDateString()],
            'this_year' => [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()],
            default => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
        };
    }

    private function getPeriodLabel(string $period): string
    {
        return match ($period) {
            'today' => 'hari ini',
            'this_week' => 'minggu ini',
            'last_week' => 'minggu lalu',
            'this_month' => 'bulan ini',
            'last_month' => 'bulan lalu',
            'this_year' => 'tahun ini',
            default => 'bulan ini',
        };
    }
}
