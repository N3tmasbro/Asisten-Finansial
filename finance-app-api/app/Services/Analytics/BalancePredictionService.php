<?php

namespace App\Services\Analytics;

use App\Repositories\TransactionRepository;
use Illuminate\Support\Carbon;

class BalancePredictionService
{
    public function __construct(
        private TransactionRepository $transactionRepo,
    ) {}

    /**
     * Predict when balance will run out based on current spending pattern.
     * Uses simple moving average of the last 30 days.
     */
    public function predict(int $userId): array
    {
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30)->toDateString();
        $today = $now->toDateString();

        // Get current total balance across all wallets
        $totalBalance = \App\Models\Wallet::where('user_id', $userId)->sum('balance');

        // Calculate daily average spending (last 30 days)
        $dailyAvgExpense = $this->transactionRepo->dailyAverage($userId, $thirtyDaysAgo, $today);

        // Calculate daily average income (last 30 days)
        $totalIncome = $this->transactionRepo->totalForPeriod($userId, $thirtyDaysAgo, $today, 'income');
        $dailyAvgIncome = $totalIncome / 30;

        // Net daily burn
        $dailyNetBurn = $dailyAvgExpense - $dailyAvgIncome;

        // Predict when balance runs out
        $daysUntilEmpty = null;
        $predictedDate = null;

        if ($dailyNetBurn > 0 && $totalBalance > 0) {
            $daysUntilEmpty = (int) ceil($totalBalance / $dailyNetBurn);
            $predictedDate = $now->copy()->addDays($daysUntilEmpty)->toDateString();
        }

        // Predict end-of-month balance
        $daysLeft = $now->copy()->endOfMonth()->diffInDays($now);
        $predictedMonthEndBalance = $totalBalance - ($dailyNetBurn * $daysLeft);

        return [
            'current_balance' => (int) $totalBalance,
            'daily_avg_expense' => (int) $dailyAvgExpense,
            'daily_avg_income' => (int) $dailyAvgIncome,
            'daily_net_burn' => (int) $dailyNetBurn,
            'days_until_empty' => $daysUntilEmpty,
            'predicted_empty_date' => $predictedDate,
            'predicted_month_end_balance' => (int) max(0, $predictedMonthEndBalance),
            'days_left_in_month' => $daysLeft,
        ];
    }
}
