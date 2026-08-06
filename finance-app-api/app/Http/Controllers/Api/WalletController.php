<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * List all wallets for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $wallets = $request->user()->wallets()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn($wallet) => [
                'id' => $wallet->id,
                'name' => $wallet->name,
                'type' => $wallet->type,
                'balance' => $wallet->balance,
                'formatted_balance' => $wallet->formatted_balance,
                'is_default' => $wallet->is_default,
            ]);

        $totalBalance = $wallets->sum('balance');

        return response()->json([
            'wallets' => $wallets,
            'total_balance' => $totalBalance,
            'formatted_total' => 'Rp' . number_format($totalBalance, 0, ',', '.'),
        ]);
    }

    /**
     * Create a new wallet.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'type' => 'required|in:cash,bank,ewallet',
            'balance' => 'nullable|integer|min:0',
        ]);

        $user = $request->user();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'balance' => $validated['balance'] ?? 0,
            'is_default' => !$user->wallets()->exists(), // First wallet is default
        ]);

        return response()->json([
            'message' => 'Dompet berhasil ditambahkan.',
            'wallet' => $wallet,
        ], 201);
    }

    /**
     * Update a wallet.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->wallets()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:50',
            'type' => 'sometimes|in:cash,bank,ewallet',
            'is_default' => 'sometimes|boolean',
        ]);

        // If setting as default, unset other defaults
        if ($request->boolean('is_default')) {
            $user->wallets()->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $wallet->update($validated);

        return response()->json([
            'message' => 'Dompet berhasil diperbarui.',
            'wallet' => $wallet,
        ]);
    }
}
