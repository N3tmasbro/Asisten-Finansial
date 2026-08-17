<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use App\Services\Transaction\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionService $transactionService,
        private TransactionRepository $transactionRepo,
    ) {}

    /**
     * List transactions with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Transaction::where('user_id', $user->id)
            ->with(['category:id,name,icon,type', 'wallet:id,name,type']);

        // Filters
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('wallet_id')) {
            $query->where('wallet_id', $request->input('wallet_id'));
        }

        if ($request->has('date_from')) {
            $query->where('transaction_date', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('transaction_date', '<=', $request->input('date_to'));
        }

        if ($request->boolean('needs_review')) {
            $query->where('is_reviewed', false);
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                    ->orWhere('raw_input', 'LIKE', "%{$search}%");
            });
        }

        $transactions = $query->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($transactions);
    }

    /**
     * Create a new transaction (manual from web dashboard).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'wallet_id'        => 'required|exists:wallets,id',
            'category_id'      => [
                'required',
                'integer',
                // Only allow global default categories or categories owned by this user
                function ($attribute, $value, $fail) use ($user) {
                    $exists = \App\Models\Category::where('id', $value)
                        ->where(function ($q) use ($user) {
                            $q->whereNull('user_id')      // global defaults
                              ->orWhere('user_id', $user->id); // user's own
                        })
                        ->exists();
                    if (!$exists) {
                        $fail('Kategori tidak valid atau tidak dapat diakses.');
                    }
                },
            ],
            'type'             => 'required|in:expense,income',
            'amount'           => 'required|integer|min:1',
            'description'      => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
        ]);

        // Verify wallet belongs to user
        $wallet = $user->wallets()->findOrFail($validated['wallet_id']);

        $transaction = Transaction::create(array_merge($validated, [
            'user_id'     => $user->id,
            'is_reviewed' => true,
        ]));

        // Update wallet balance
        $balanceChange = $validated['type'] === 'income' ? $validated['amount'] : -$validated['amount'];
        $wallet->adjustBalance($balanceChange);

        $transaction->load(['category', 'wallet']);

        return response()->json([
            'message'     => 'Transaksi berhasil ditambahkan.',
            'transaction' => $transaction,
        ], 201);
    }

    /**
     * Update a transaction.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $transaction = Transaction::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'wallet_id'        => 'sometimes|exists:wallets,id',
            'category_id'      => [
                'sometimes',
                'integer',
                // Only allow global default categories or categories owned by this user
                function ($attribute, $value, $fail) use ($user) {
                    $exists = \App\Models\Category::where('id', $value)
                        ->where(function ($q) use ($user) {
                            $q->whereNull('user_id')
                              ->orWhere('user_id', $user->id);
                        })
                        ->exists();
                    if (!$exists) {
                        $fail('Kategori tidak valid atau tidak dapat diakses.');
                    }
                },
            ],
            'type'             => 'sometimes|in:expense,income',
            'amount'           => 'sometimes|integer|min:1',
            'description'      => 'nullable|string|max:255',
            'transaction_date' => 'sometimes|date',
            'is_reviewed'      => 'sometimes|boolean',
        ]);

        $transaction = $this->transactionService->update($transaction, $validated);
        $transaction->load(['category', 'wallet']);

        return response()->json([
            'message'     => 'Transaksi berhasil diperbarui.',
            'transaction' => $transaction,
        ]);
    }

    /**
     * Delete a transaction.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $transaction = Transaction::where('user_id', $user->id)->findOrFail($id);

        $this->transactionService->delete($transaction);

        return response()->json([
            'message' => 'Transaksi berhasil dihapus.',
        ]);
    }
}
