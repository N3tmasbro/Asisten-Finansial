<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    /**
     * List all budgets for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $budgets = $request->user()->budgets()
            ->with('category:id,name,icon,type')
            ->get()
            ->map(fn($budget) => [
                'id' => $budget->id,
                'category_id' => $budget->category_id,
                'category' => $budget->category,
                'amount' => $budget->amount,
                'formatted_amount' => $budget->formatted_amount,
                'period_type' => $budget->period_type,
                'used' => $budget->getUsedAmount(),
                'usage_percentage' => $budget->getUsagePercentage(),
                'remaining' => max(0, $budget->amount - $budget->getUsedAmount()),
            ]);

        return response()->json([
            'budgets' => $budgets,
        ]);
    }

    /**
     * Create a new budget.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'amount' => 'required|integer|min:1',
            'period_type' => 'nullable|in:monthly,weekly',
        ]);

        $user = $request->user();

        // Check for existing budget on same category + period
        $exists = Budget::where('user_id', $user->id)
            ->where('category_id', $validated['category_id'])
            ->where('period_type', $validated['period_type'] ?? 'monthly')
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Budget untuk kategori dan periode ini sudah ada. Gunakan update.',
            ], 422);
        }

        $budget = Budget::create([
            'user_id' => $user->id,
            'category_id' => $validated['category_id'],
            'amount' => $validated['amount'],
            'period_type' => $validated['period_type'] ?? 'monthly',
        ]);

        $budget->load('category');

        return response()->json([
            'message' => 'Budget berhasil dibuat.',
            'budget' => $budget,
        ], 201);
    }

    /**
     * Update a budget.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $budget = Budget::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
        ]);

        $budget->update($validated);

        return response()->json([
            'message' => 'Budget berhasil diperbarui.',
            'budget' => $budget->load('category'),
        ]);
    }

    /**
     * Delete a budget.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $budget = Budget::where('user_id', $user->id)->findOrFail($id);

        $budget->delete();

        return response()->json([
            'message' => 'Budget berhasil dihapus.',
        ]);
    }
}
