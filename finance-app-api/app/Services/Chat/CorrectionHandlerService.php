<?php

namespace App\Services\Chat;

use App\Contracts\AIProviderInterface;
use App\Models\ChatMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionService;
use Illuminate\Support\Facades\Log;

class CorrectionHandlerService
{
    public function __construct(
        private AIProviderInterface $aiProvider,
        private TransactionService $transactionService,
    ) {}

    /**
     * Handle a correction request from the user.
     * Tries to identify which transaction(s) to correct based on context.
     */
    public function handle(User $user, string $message, ChatMessage $chatMessage): string
    {
        // Find the most recent transaction(s) by this user
        $recentTransactions = Transaction::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->with(['category', 'wallet'])
            ->get();

        if ($recentTransactions->isEmpty()) {
            return "Hmm, aku belum menemukan transaksi yang bisa dikoreksi 🤔";
        }

        // Try to parse what the user wants to correct
        $correctionData = $this->parseCorrectionIntent($message, $recentTransactions);

        if (!$correctionData) {
            // Show recent transactions and ask which one to correct
            $lines = ["Transaksi terbaru kamu:\n"];
            foreach ($recentTransactions->take(3) as $i => $tx) {
                $amount = number_format($tx->amount, 0, ',', '.');
                $lines[] = ($i + 1) . ". {$tx->description} — Rp{$amount} [{$tx->category->name}]";
            }
            $lines[] = "\nMau koreksi yang mana? Kirim misalnya: \"yang nomor 1 harusnya 50rb\"";

            return implode("\n", $lines);
        }

        // Apply correction
        try {
            $transaction = $recentTransactions->find($correctionData['transaction_id'])
                ?? $recentTransactions->first();

            $updateData = [];

            if (isset($correctionData['amount'])) {
                $updateData['amount'] = $correctionData['amount'];
            }
            if (isset($correctionData['category_id'])) {
                $updateData['category_id'] = $correctionData['category_id'];
            }
            if (isset($correctionData['description'])) {
                $updateData['description'] = $correctionData['description'];
            }

            if (!empty($updateData)) {
                $this->transactionService->update($transaction, $updateData);

                return $this->aiProvider->formatResponse('correction_confirmation', [
                    'summary' => "Transaksi \"{$transaction->description}\" sudah diperbarui.",
                    'transaction' => $transaction->fresh(['category', 'wallet'])->toArray(),
                ]);
            }

            return "Aku kurang paham koreksinya 🤔 Coba kasih detail, misalnya: \"yang tadi harusnya 50rb\" atau \"yang bensin salah kategori, harusnya transport\"";

        } catch (\Exception $e) {
            Log::error('Correction error', [
                'user_id' => $user->id,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            return "Maaf, gagal mengoreksi 😓 Coba lagi atau edit langsung di dashboard ya!";
        }
    }

    /**
     * Try to parse what the user wants to correct from their message.
     */
    private function parseCorrectionIntent(string $message, $recentTransactions): ?array
    {
        $message = mb_strtolower($message);

        // Check for "nomor X" or "yang ke-X" pattern
        if (preg_match('/(nomor|no|ke[- ]?)(\d+)/i', $message, $matches)) {
            $index = (int) $matches[2] - 1;
            $transaction = $recentTransactions->values()->get($index);

            if ($transaction) {
                $data = ['transaction_id' => $transaction->id];

                // Check for amount correction
                $amount = $this->extractAmount($message);
                if ($amount > 0) {
                    $data['amount'] = $amount;
                }

                return $data;
            }
        }

        // Check for "yang tadi" (most recent)
        if (preg_match('/(yang tadi|yang barusan|yang terakhir)/i', $message)) {
            $transaction = $recentTransactions->first();
            $data = ['transaction_id' => $transaction->id];

            $amount = $this->extractAmount($message);
            if ($amount > 0) {
                $data['amount'] = $amount;
            }

            return $data;
        }

        // Try to match by description keyword
        foreach ($recentTransactions as $tx) {
            $desc = mb_strtolower($tx->description);
            if (str_contains($message, $desc) || str_contains($desc, $this->extractKeyword($message))) {
                $data = ['transaction_id' => $tx->id];

                $amount = $this->extractAmount($message);
                if ($amount > 0) {
                    $data['amount'] = $amount;
                }

                return $data;
            }
        }

        return null;
    }

    private function extractAmount(string $text): int
    {
        if (preg_match('/(\d+[\.,]?\d*)\s*(juta|jt)/i', $text, $matches)) {
            $num = str_replace(',', '.', $matches[1]);
            return (int) ((float) $num * 1000000);
        }

        if (preg_match('/(\d+[\.,]?\d*)\s*(ribu|rb|k)\b/i', $text, $matches)) {
            $num = str_replace(',', '.', $matches[1]);
            return (int) ((float) $num * 1000);
        }

        if (preg_match('/(\d{1,3}(?:\.\d{3})+)/', $text, $matches)) {
            return (int) str_replace('.', '', $matches[1]);
        }

        return 0;
    }

    private function extractKeyword(string $text): string
    {
        // Remove correction-related words to get the transaction keyword
        $cleaned = preg_replace('/(salah|koreksi|ubah|ganti|bukan|ralat|harusnya|seharusnya|yang|tadi|barusan)/i', '', $text);
        $cleaned = preg_replace('/\d+[\.,]?\d*\s*(ribu|rb|juta|jt|k)\b/i', '', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return trim($cleaned);
    }
}
