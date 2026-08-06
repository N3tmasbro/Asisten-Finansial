<?php

namespace App\Services\Transaction;

use App\DTOs\ParsedTransactionDTO;
use App\Enums\TransactionType;
use App\Models\ChatMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    public function __construct(
        private CategoryMatcherService $categoryMatcher,
    ) {}

    /**
     * Create transaction(s) from AI-parsed data.
     * Handles wallet balance updates within a DB transaction.
     *
     * @param User $user
     * @param ParsedTransactionDTO[] $parsedTransactions
     * @param ChatMessage|null $chatMessage
     * @return Transaction[]
     */
    public function createFromParsed(User $user, array $parsedTransactions, ?ChatMessage $chatMessage = null): array
    {
        $transactions = [];

        DB::transaction(function () use ($user, $parsedTransactions, $chatMessage, &$transactions) {
            foreach ($parsedTransactions as $parsed) {
                $wallet = $this->resolveWallet($user, $parsed->walletName);
                $category = $this->categoryMatcher->match(
                    $parsed->categoryName ?? 'Lainnya',
                    $user->id,
                    $parsed->type->value
                );

                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'category_id' => $category->id,
                    'chat_message_id' => $chatMessage?->id,
                    'type' => $parsed->type,
                    'amount' => $parsed->amount,
                    'description' => $parsed->description,
                    'raw_input' => $chatMessage?->body,
                    'transaction_date' => $parsed->transactionDate ?? now()->toDateString(),
                    'ai_confidence' => $parsed->confidence,
                    'is_reviewed' => !$parsed->isLowConfidence(),
                ]);

                // Update wallet balance
                $balanceChange = $parsed->type === TransactionType::Income
                    ? $parsed->amount
                    : -$parsed->amount;

                $wallet->adjustBalance($balanceChange);

                $transaction->load(['category', 'wallet']);
                $transactions[] = $transaction;
            }
        });

        Log::info('Transactions created', [
            'user_id' => $user->id,
            'count' => count($transactions),
            'chat_message_id' => $chatMessage?->id,
        ]);

        return $transactions;
    }

    /**
     * Update a transaction manually (from web dashboard or correction).
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $oldAmount = $transaction->amount;
            $oldType = $transaction->type;
            $oldWalletId = $transaction->wallet_id;

            // Reverse old balance change
            $oldWallet = Wallet::findOrFail($oldWalletId);
            $oldReversal = $oldType === TransactionType::Income ? -$oldAmount : $oldAmount;
            $oldWallet->adjustBalance($oldReversal);

            // Update the transaction
            $transaction->update(array_merge($data, [
                'corrected_at' => now(),
                'is_reviewed' => true,
            ]));

            $transaction->refresh();

            // Apply new balance change
            $newWallet = Wallet::findOrFail($transaction->wallet_id);
            $newChange = $transaction->type === TransactionType::Income
                ? $transaction->amount
                : -$transaction->amount;
            $newWallet->adjustBalance($newChange);

            return $transaction;
        });
    }

    /**
     * Delete a transaction and reverse its balance effect.
     */
    public function delete(Transaction $transaction): bool
    {
        return DB::transaction(function () use ($transaction) {
            $wallet = $transaction->wallet;
            $reversal = $transaction->type === TransactionType::Income
                ? -$transaction->amount
                : $transaction->amount;

            $wallet->adjustBalance($reversal);

            return $transaction->delete();
        });
    }

    /**
     * Resolve wallet by name or return default wallet.
     */
    private function resolveWallet(User $user, ?string $walletName): Wallet
    {
        if ($walletName) {
            $wallet = $user->wallets()
                ->where('name', 'LIKE', '%' . $walletName . '%')
                ->first();

            if ($wallet) {
                return $wallet;
            }
        }

        // Get default wallet or create one
        $defaultWallet = $user->wallets()->where('is_default', true)->first();

        if (!$defaultWallet) {
            $defaultWallet = $user->wallets()->create([
                'name' => 'Cash',
                'type' => 'cash',
                'balance' => 0,
                'is_default' => true,
            ]);
        }

        return $defaultWallet;
    }
}
