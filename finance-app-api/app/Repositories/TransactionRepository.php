<?php

namespace App\Repositories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionRepository
{
    /**
     * Get total spending/income by category for a user in a date range.
     */
    public function totalByCategory(int $userId, string $from, string $to, string $type = 'expense'): Collection
    {
        return Transaction::where('user_id', $userId)
            ->where('type', $type)
            ->whereBetween('transaction_date', [$from, $to])
            ->select('category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category_id')
            ->with('category:id,name,icon')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Get total spending/income per day in a date range.
     */
    public function totalByDay(int $userId, string $from, string $to, ?string $type = null): Collection
    {
        $query = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$from, $to]);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->select(
                'transaction_date',
                'type',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('transaction_date', 'type')
            ->orderBy('transaction_date')
            ->get();
    }

    /**
     * Get top N spending categories.
     */
    public function topSpending(int $userId, string $from, string $to, int $limit = 5): Collection
    {
        return $this->totalByCategory($userId, $from, $to, 'expense')
            ->take($limit);
    }

    /**
     * Get total for a specific period.
     */
    public function totalForPeriod(int $userId, string $from, string $to, ?string $type = null, ?int $categoryId = null): int
    {
        $query = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$from, $to]);

        if ($type) {
            $query->where('type', $type);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return (int) $query->sum('amount');
    }

    /**
     * Get daily average spending for a period.
     */
    public function dailyAverage(int $userId, string $from, string $to): float
    {
        $total = $this->totalForPeriod($userId, $from, $to, 'expense');
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

        return $days > 0 ? $total / $days : 0;
    }

    /**
     * Get recent transactions for a user.
     */
    public function recent(int $userId, int $limit = 10): Collection
    {
        return Transaction::where('user_id', $userId)
            ->with(['category:id,name,icon', 'wallet:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get transactions that need review (low AI confidence).
     */
    public function needsReview(int $userId): Collection
    {
        return Transaction::where('user_id', $userId)
            ->where('is_reviewed', false)
            ->with(['category:id,name,icon', 'wallet:id,name'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get summary data for dashboard overview.
     */
    public function dashboardSummary(int $userId): array
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth()->toDateString();

        $totalExpenseThisMonth = $this->totalForPeriod($userId, $monthStart, $monthEnd, 'expense');
        $totalIncomeThisMonth = $this->totalForPeriod($userId, $monthStart, $monthEnd, 'income');
        $totalExpenseLastMonth = $this->totalForPeriod($userId, $lastMonthStart, $lastMonthEnd, 'expense');
        $totalIncomeLastMonth = $this->totalForPeriod($userId, $lastMonthStart, $lastMonthEnd, 'income');

        return [
            'this_month' => [
                'total_expense' => $totalExpenseThisMonth,
                'total_income' => $totalIncomeThisMonth,
                'net' => $totalIncomeThisMonth - $totalExpenseThisMonth,
            ],
            'last_month' => [
                'total_expense' => $totalExpenseLastMonth,
                'total_income' => $totalIncomeLastMonth,
                'net' => $totalIncomeLastMonth - $totalExpenseLastMonth,
            ],
            'expense_change_percent' => $totalExpenseLastMonth > 0
                ? round(($totalExpenseThisMonth - $totalExpenseLastMonth) / $totalExpenseLastMonth * 100, 1)
                : 0,
            'daily_average' => $this->dailyAverage($userId, $monthStart, $now->toDateString()),
            'review_count' => Transaction::where('user_id', $userId)->where('is_reviewed', false)->count(),
        ];
    }
}
