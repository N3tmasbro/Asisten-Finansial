<?php

namespace App\Services\Analytics;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BalancePredictionService
{
    /**
     * Minimum lookback days to avoid extreme volatility for new users.
     */
    private const MIN_LOOKBACK_DAYS = 7;

    /**
     * Maximum lookback days for burn rate calculation.
     */
    private const MAX_LOOKBACK_DAYS = 30;

    /**
     * Top percentile of transactions to trim as outliers (10% = 0.10).
     */
    private const OUTLIER_TRIM_PERCENTILE = 0.10;

    /**
     * Daily net burn threshold (in Rupiah) below which trend is considered "stable".
     */
    private const STABLE_THRESHOLD = 5000;

    public function __construct(
        private TransactionRepository $transactionRepo,
    ) {}

    /**
     * Predict when balance will run out based on current spending pattern.
     *
     * Uses trimmed daily averages (top 10% outliers removed) over a dynamic
     * lookback window (min 7 days, max 30 days based on user transaction history).
     */
    public function predict(int $userId): array
    {
        $now = Carbon::now();

        // ── 1. Get current total balance across all wallets ──
        $totalBalance = (int) Wallet::where('user_id', $userId)->sum('balance');

        // ── 2. Determine lookback period (dynamic) ──
        $firstTransaction = Transaction::where('user_id', $userId)
            ->orderBy('transaction_date')
            ->value('transaction_date');

        if (!$firstTransaction) {
            // No transactions at all → unknown trend
            return $this->buildResponse($totalBalance, 0, 0, 0, $now, 'unknown');
        }

        $firstDate = Carbon::parse($firstTransaction);
        $daysSinceFirst = (int) $firstDate->diffInDays($now);
        $lookbackDays = max(self::MIN_LOOKBACK_DAYS, min($daysSinceFirst, self::MAX_LOOKBACK_DAYS));

        $from = $now->copy()->subDays($lookbackDays)->toDateString();
        $to = $now->toDateString();

        // ── 3. Get all expense & income amounts in the lookback period ──
        $expenses = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$from, $to])
            ->pluck('amount');

        $incomes = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$from, $to])
            ->pluck('amount');

        if ($expenses->isEmpty() && $incomes->isEmpty()) {
            return $this->buildResponse($totalBalance, 0, 0, 0, $now, 'unknown');
        }

        // ── 4. Trim top 10% outliers from both sets ──
        $trimmedExpenseTotal = $this->trimmedSum($expenses);
        $trimmedIncomeTotal = $this->trimmedSum($incomes);

        // ── 5. Calculate daily averages (trimmed) ──
        $dailyAvgExpense = $lookbackDays > 0 ? $trimmedExpenseTotal / $lookbackDays : 0;
        $dailyAvgIncome = $lookbackDays > 0 ? $trimmedIncomeTotal / $lookbackDays : 0;
        $dailyNetBurn = $dailyAvgExpense - $dailyAvgIncome;

        // ── 6. Classify trend ──
        $trend = $this->classifyTrend($dailyNetBurn);

        return $this->buildResponse(
            $totalBalance,
            (int) round($dailyAvgExpense),
            (int) round($dailyAvgIncome),
            (int) round($dailyNetBurn),
            $now,
            $trend,
            $lookbackDays,
        );
    }

    /**
     * Calculate the sum of a collection after removing the top 10% largest values.
     * The removed values are still counted — they just don't contribute to the "routine" average.
     */
    private function trimmedSum(Collection $amounts): int
    {
        if ($amounts->isEmpty()) {
            return 0;
        }

        $sorted = $amounts->sort()->values();
        $count = $sorted->count();

        // Number of items to trim from the top
        $trimCount = (int) ceil($count * self::OUTLIER_TRIM_PERCENTILE);

        // If we'd trim everything, keep at least 1 item
        if ($trimCount >= $count) {
            $trimCount = max(0, $count - 1);
        }

        // Take all items except the top N
        $trimmed = $sorted->slice(0, $count - $trimCount);

        return (int) $trimmed->sum();
    }

    /**
     * Classify the financial trend based on net daily burn.
     */
    private function classifyTrend(float $dailyNetBurn): string
    {
        if (abs($dailyNetBurn) <= self::STABLE_THRESHOLD) {
            return 'stable';
        }

        return $dailyNetBurn > 0 ? 'burning' : 'saving';
    }

    /**
     * Build the standardized prediction response array.
     */
    private function buildResponse(
        int $totalBalance,
        int $dailyAvgExpense,
        int $dailyAvgIncome,
        int $dailyNetBurn,
        Carbon $now,
        string $trend,
        int $lookbackDays = 0,
    ): array {
        $daysUntilEmpty = null;
        $predictedDate = null;

        // Only calculate empty date if user is burning money and has positive balance
        if ($trend === 'burning' && $dailyNetBurn > 0 && $totalBalance > 0) {
            $daysUntilEmpty = (int) ceil($totalBalance / $dailyNetBurn);
            $predictedDate = $now->copy()->addDays($daysUntilEmpty)->toDateString();
        }

        // Predict end-of-month balance
        $daysLeftInMonth = (int) $now->copy()->endOfMonth()->diffInDays($now);
        $predictedMonthEndBalance = $totalBalance - ($dailyNetBurn * $daysLeftInMonth);

        // Daily savings rate (positive when saving, negative when burning)
        $dailySavingsRate = -$dailyNetBurn; // invert: positive = saving

        return [
            'current_balance' => $totalBalance,
            'daily_avg_expense' => $dailyAvgExpense,
            'daily_avg_income' => $dailyAvgIncome,
            'daily_net_burn' => $dailyNetBurn,
            'daily_savings_rate' => (int) $dailySavingsRate,
            'trend' => $trend,
            'days_until_empty' => $daysUntilEmpty,
            'predicted_empty_date' => $predictedDate,
            'predicted_month_end_balance' => (int) $predictedMonthEndBalance,
            'days_left_in_month' => $daysLeftInMonth,
            'lookback_days' => $lookbackDays,
        ];
    }
}
