<?php

namespace App\Services\Chat;

use App\Contracts\AIProviderInterface;
use App\DTOs\FinancialCommandDTO;
use App\Models\Category;
use App\Models\ChatMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\CategoryMatcherService;
use App\Services\Transaction\TransactionService;
use Illuminate\Support\Facades\Log;

/**
 * Handles transaction correction requests via WhatsApp.
 *
 * Flow:
 * 1. AI parses correction intent → FinancialCommandDTO
 * 2. TransactionCandidateResolver finds matching transaction(s)
 * 3. 0 matches → "not found", 1 match → apply correction, N matches → ask user
 * 4. Uses existing TransactionService::update() for wallet balance handling
 */
class CorrectionHandlerService
{
    public function __construct(
        private AIProviderInterface $aiProvider,
        private TransactionService $transactionService,
        private TransactionCandidateResolver $candidateResolver,
        private CategoryMatcherService $categoryMatcher,
    ) {}

    /**
     * Handle a correction request from the user.
     */
    public function handle(User $user, string $message, ChatMessage $chatMessage): string|array
    {
        try {
            // Build context for AI
            $context = $this->buildContext($user);

            // Parse correction command via AI
            $command = $this->aiProvider->parseFinancialCommand($message, $context);

            if ($command->action !== 'update_transaction') {
                return "Aku kurang paham koreksinya 🤔 Coba kasih detail, misalnya: \"yang tadi harusnya 50rb\" atau \"yang bensin salah kategori, harusnya transport\"";
            }

            // Resolve candidate transactions
            $candidates = $this->candidateResolver->resolve($user, $command->target);

            if ($candidates->isEmpty()) {
                return "Hmm, aku nggak menemukan transaksi yang cocok 🤔 Coba jelaskan lebih detail ya.";
            }

            if ($candidates->count() === 1) {
                return $this->applyCorrection($candidates->first(), $command, $user);
            }

            // Multiple candidates — ask user to choose
            $formatted = $this->candidateResolver->formatCandidateList($candidates, 'koreksi');

            // Return array with pending selection metadata
            return [
                'text' => $formatted['text'],
                'pending_selection' => [
                    'action' => 'correction',
                    'candidates' => $formatted['candidates'],
                    'original_changes' => $command->changes,
                    'expires_minutes' => 10,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Correction handler error', [
                'user_id' => $user->id,
                'message' => $message,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return "Maaf, gagal mengoreksi 😓 Coba lagi atau edit langsung di dashboard ya!";
        }
    }

    /**
     * Apply a correction to a specific transaction (called when 1 candidate or after user selection).
     */
    public function applyCorrection(Transaction $transaction, FinancialCommandDTO $command, User $user): string
    {
        // Verify ownership
        if ($transaction->user_id !== $user->id) {
            return "Transaksi ini bukan milikmu 🚫";
        }

        $updateData = [];
        $changeSummary = [];

        // Amount change
        if ($command->change('amount') !== null) {
            $oldAmount = $transaction->amount;
            $newAmount = (int) $command->change('amount');
            if ($newAmount > 0) {
                $updateData['amount'] = $newAmount;
                $changeSummary[] = "nominal dari Rp" . number_format($oldAmount, 0, ',', '.') . " → Rp" . number_format($newAmount, 0, ',', '.');
            }
        }

        // Category change
        if ($command->change('category') !== null) {
            $newCategory = $this->categoryMatcher->match(
                $command->change('category'),
                $user->id,
                $transaction->type->value
            );
            $oldCategoryName = $transaction->category->name ?? 'Unknown';
            $updateData['category_id'] = $newCategory->id;
            $changeSummary[] = "kategori dari {$oldCategoryName} → {$newCategory->name}";
        }

        // Wallet change
        if ($command->change('wallet') !== null) {
            $newWallet = $user->wallets()
                ->where('name', 'LIKE', '%' . $command->change('wallet') . '%')
                ->first();

            if ($newWallet) {
                $oldWalletName = $transaction->wallet->name ?? 'Unknown';
                $updateData['wallet_id'] = $newWallet->id;
                $changeSummary[] = "wallet dari {$oldWalletName} → {$newWallet->name}";
            } else {
                return "Wallet \"{$command->change('wallet')}\" tidak ditemukan 🤔 Cek nama wallet kamu.";
            }
        }

        // Description change
        if ($command->change('description') !== null) {
            $updateData['description'] = $command->change('description');
            $changeSummary[] = "deskripsi → \"{$command->change('description')}\"";
        }

        // Date change
        if ($command->change('date') !== null) {
            try {
                $updateData['transaction_date'] = $command->change('date');
                $changeSummary[] = "tanggal → {$command->change('date')}";
            } catch (\Exception $e) {
                // Invalid date, skip
            }
        }

        // Type change (financially significant!)
        if ($command->change('type') !== null) {
            $newType = $command->change('type');
            if (in_array($newType, ['income', 'expense'])) {
                $oldType = $transaction->type->value;
                if ($oldType !== $newType) {
                    $updateData['type'] = $newType;
                    $typeLabel = $newType === 'income' ? 'pemasukan' : 'pengeluaran';
                    $changeSummary[] = "tipe → {$typeLabel}";
                }
            }
        }

        if (empty($updateData)) {
            return "Aku kurang paham apa yang mau dikoreksi 🤔 Coba jelaskan lebih spesifik ya.";
        }

        // Apply correction via TransactionService (handles wallet balance reversal/reapplication)
        $this->transactionService->update($transaction, $updateData);

        $summary = implode(', ', $changeSummary);
        return "Siap! ✏️ Transaksi \"{$transaction->description}\" sudah diubah: {$summary}.";
    }

    /**
     * Apply a correction by transaction ID (used after candidate selection).
     */
    public function applyCorrectionById(User $user, int $transactionId, ?array $changes): string
    {
        $transaction = Transaction::where('user_id', $user->id)->find($transactionId);

        if (!$transaction) {
            return "Transaksi tidak ditemukan 🤔";
        }

        if (!$changes || empty(array_filter($changes, fn($v) => $v !== null))) {
            return "Tidak ada perubahan yang diterapkan.";
        }

        $command = FinancialCommandDTO::fromAIResponse([
            'action' => 'update_transaction',
            'changes' => $changes,
        ]);

        return $this->applyCorrection($transaction, $command, $user);
    }

    /**
     * Build context data for AI parsing.
     */
    private function buildContext(User $user): array
    {
        $recentTx = Transaction::where('user_id', $user->id)
            ->with(['category:id,name', 'wallet:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return [
            'recent_transactions' => $recentTx->map(fn($tx) => [
                'description' => $tx->description,
                'amount' => $tx->amount,
                'category' => $tx->category->name ?? 'Unknown',
                'wallet' => $tx->wallet->name ?? 'Unknown',
                'date' => $tx->transaction_date->format('Y-m-d'),
            ])->toArray(),
            'wallets' => $user->wallets()->pluck('name')->toArray(),
            'categories_expense' => $this->categoryMatcher->getCategoryNamesForUser($user->id, 'expense'),
            'categories_income' => $this->categoryMatcher->getCategoryNamesForUser($user->id, 'income'),
        ];
    }
}
