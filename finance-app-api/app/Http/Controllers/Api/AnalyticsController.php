<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\TransactionRepository;
use App\Services\Analytics\BalancePredictionService;
use App\Services\Analytics\SummaryService;
use App\Services\Analytics\TrendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private TransactionRepository $transactionRepo,
        private SummaryService $summaryService,
        private TrendService $trendService,
        private BalancePredictionService $predictionService,
    ) {}

    /**
     * Dashboard summary overview.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->input('period', 'this_month');

        $summary = $this->summaryService->getSummary($user->id, $period);
        $dashboard = $this->transactionRepo->dashboardSummary($user->id);
        $recentTransactions = $this->transactionRepo->recent($user->id, 5);

        return response()->json([
            'summary' => $summary,
            'dashboard' => $dashboard,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    /**
     * Spending trends (period comparison + daily trend).
     */
    public function trends(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->input('period', 'this_month');

        $comparison = $this->trendService->comparePeriods($user->id);
        $dailyTrend = $this->trendService->dailyTrend($user->id, $period);
        $categoryTrend = $this->trendService->categoryTrend($user->id);

        return response()->json([
            'comparison' => $comparison,
            'daily_trend' => $dailyTrend,
            'category_trend' => $categoryTrend,
        ]);
    }

    /**
     * Category breakdown.
     */
    public function categoryBreakdown(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->input('period', 'this_month');
        $type = $request->input('type', 'expense');

        $breakdown = $this->summaryService->getCategoryBreakdown($user->id, $period, $type);

        return response()->json($breakdown);
    }

    /**
     * Balance prediction.
     */
    public function balancePrediction(Request $request): JsonResponse
    {
        $user = $request->user();

        $prediction = $this->predictionService->predict($user->id);

        return response()->json($prediction);
    }
}
