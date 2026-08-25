<?php

namespace App\Services\Chat;

use App\Contracts\AIProviderInterface;
use App\Models\ChatMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\CategoryMatcherService;
use App\Services\Transaction\TransactionService;
use Illuminate\Support\Facades\Log;

/**
 * Handles transaction deletion requests via WhatsApp.
 *
 * Flow:
 * 1. AI parses deletion target → FinancialCommandDTO
 * 2. TransactionCandidateResolver finds matching transaction(s)
 * 3. 0 matches → "not found", 1 match → ask confirmation, N matches → ask user to pick
 * 4. Confirmation stored as pending_confirmation in outgoing message
 * 5. On confirmation: TransactionService::delete() handles wallet balance reversal + soft delete
 */
class DeleteHandlerService
{
    public function __construct(
        private AIProviderInterface $aiProvider,
        private TransactionService $transactionService,
        private TransactionCandidateResolver $candidateResolver,
        private CategoryMatcherService $categoryMatcher,
    ) {}

    /**
     * Handle a delete request from the user.
     */
    public function handle(User $user, string $message, ChatMessage $chatMessage): string|array
    {
        try {
            // Build context for AI
            $context = $this->buildContext($user);

            // Parse deletion command via AI
            $command = $this->aiProvider->parseFinancialCommand($message, $context);

            if ($command->action !== 'delete_transaction') {
                return "Aku kurang paham yang mau dihapus 🤔 Coba bilang misalnya: \"hapus yang bensin tadi\"";
            }

            // Resolve candidate transactions
            $candidates = $this->candidateResolver->resolve($user, $command->target);

            if ($candidates->isEmpty()) {
                return "Hmm, aku nggak menemukan transaksi yang cocok untuk dihapus 🤔";
            }

            if ($candidates->count() === 1) {
                return $this->requestConfirmation($candidates->first());
            }

            // Multiple candidates — ask user to choose
            $formatted = $this->candidateResolver->formatCandidateList($candidates, 'hapus');

            return [
                'text' => $formatted['text'],
                'pending_selection' => [
                    'action' => 'delete',
                    'candidates' => $formatted['candidates'],
                    'original_changes' => null,
                    'expires_minutes' => 10,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Delete handler error', [
                'user_id' => $user->id,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            return "Maaf, gagal memproses penghapusan 😓 Coba lagi ya!";
        }
    }

    /**
     * Request confirmation before deleting a transaction.
     * Returns array with pending_confirmation metadata.
     */
    public function requestConfirmation(Transaction $transaction): array
    {
        $amount   = number_format($transaction->amount, 0, ',', '.');
        $isIncome = $transaction->type->value === 'income';
        $sign     = $isIncome ? '➕' : '➖';
        $wallet   = $transaction->wallet->name ?? 'Cash';
        $category = $transaction->category->name ?? 'Lainnya';
        $months   = [
            'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr',
            'May' => 'Mei', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Agu',
            'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des',
        ];
        $monthEng = $transaction->transaction_date->format('M');
        $dateStr  = $transaction->transaction_date->format('j') . ' ' . ($months[$monthEng] ?? $monthEng) . ' ' . $transaction->transaction_date->format('Y');

        $text = "⚠️ *Konfirmasi Hapus Transaksi*\n\n"
            . "{$sign} *{$transaction->description}*\n"
            . "   Rp{$amount} • {$wallet} • {$dateStr}\n"
            . "   🏷️ {$category}\n\n"
            . "Yakin ingin menghapus transaksi ini?\n"
            . "Balas *ya* untuk menghapus atau *tidak* untuk membatalkan.";

        return [
            'text' => $text,
            'pending_confirmation' => [
                'action' => 'delete_transaction',
                'transaction_id' => $transaction->id,
                'metadata' => [
                    'description' => $transaction->description,
                    'amount' => $transaction->amount,
                    'wallet' => $wallet,
                    'category' => $category,
                ],
                'created_at' => now()->toIso8601String(),
                'expires_minutes' => 10,
            ],
        ];
    }

    /**
     * Execute the confirmed deletion.
     */
    public function executeDelete(User $user, int $transactionId): string
    {
        $transaction = Transaction::where('user_id', $user->id)
            ->with(['wallet', 'category'])
            ->find($transactionId);

        if (!$transaction) {
            return "Transaksi tidak ditemukan atau sudah dihapus.";
        }

        $description = $transaction->description;
        $amount = number_format($transaction->amount, 0, ',', '.');
        $wallet = $transaction->wallet->name ?? 'Unknown';

        // TransactionService::delete() handles wallet balance reversal + soft delete
        $this->transactionService->delete($transaction);

        return "Transaksi \"{$description}\" Rp{$amount} sudah dihapus dan saldo {$wallet} sudah disesuaikan ✅";
    }

    /**
     * Request confirmation for a specific transaction from candidate list (after selection).
     */
    public function requestConfirmationById(User $user, int $transactionId): string|array
    {
        $transaction = Transaction::where('user_id', $user->id)
            ->with(['wallet', 'category'])
            ->find($transactionId);

        if (!$transaction) {
            return "Transaksi tidak ditemukan 🤔";
        }

        return $this->requestConfirmation($transaction);
    }

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
