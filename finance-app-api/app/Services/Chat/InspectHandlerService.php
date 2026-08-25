<?php

namespace App\Services\Chat;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\Log;

/**
 * Handles read-only inspection requests via WhatsApp.
 *
 * No Gemini API call — uses lightweight keyword matching.
 * All returned data comes from the database (AI never invents values).
 *
 * Supported:
 * - Recent transactions
 * - Wallet balance (single or all)
 * - Transaction detail
 * - Budget status
 * - List wallets
 * - List categories
 * - List budgets
 */
class InspectHandlerService
{
    public function __construct(
        private TransactionRepository $transactionRepo,
        private TransactionCandidateResolver $candidateResolver,
    ) {}

    /**
     * Handle an inspection request. Uses keyword matching — no AI call.
     */
    public function handle(User $user, string $message): string
    {
        $message = mb_strtolower(trim($message));

        try {
            // List wallets
            if ($this->matchesKeywords($message, ['daftar wallet', 'list wallet', 'wallet apa aja', 'wallet saya', 'wallet ku', 'semua wallet'])) {
                return $this->listWallets($user);
            }

            // List categories
            if ($this->matchesKeywords($message, ['daftar kategori', 'list kategori', 'kategori apa aja', 'kategori saya', 'semua kategori'])) {
                return $this->listCategories($user);
            }

            // List budgets
            if ($this->matchesKeywords($message, ['daftar budget', 'list budget', 'budget apa aja', 'semua budget'])) {
                return $this->listBudgets($user);
            }

            // Budget status (specific category)
            if (preg_match('/budget\s+(.+?)(\s+bulan\s+ini)?$/i', $message, $matches)) {
                $categoryHint = trim($matches[1]);
                // Filter out generic words
                if (!in_array($categoryHint, ['apa', 'berapa', 'gimana', 'saya', 'ku', 'aku'])) {
                    return $this->getBudgetStatus($user, $categoryHint);
                }
            }

            // All wallet balances
            if ($this->matchesKeywords($message, ['saldo semua', 'saldo total', 'saldo semua wallet', 'semua saldo'])) {
                return $this->getAllBalances($user);
            }

            // Specific wallet balance
            if (preg_match('/saldo\s+(.+)/i', $message, $matches)) {
                $walletHint = trim($matches[1]);
                if (!in_array($walletHint, ['berapa', 'semua', 'total', 'aku', 'ku', 'saya'])) {
                    return $this->getWalletBalance($user, $walletHint);
                }
                // "saldo berapa" = show all balances
                return $this->getAllBalances($user);
            }

            // Transaction detail (specific)
            if ($this->matchesKeywords($message, ['detail transaksi', 'detail yang'])) {
                $hint = preg_replace('/^(detail\s+(?:transaksi\s+)?(?:yang\s+)?)/i', '', $message);
                return $this->getTransactionDetail($user, trim($hint));
            }

            // Recent transactions (default for inspect)
            if ($this->matchesKeywords($message, ['transaksi terakhir', 'transaksi terbaru', 'riwayat', 'history', 'transaksi hari ini', 'apa aja yang dicatat'])) {
                return $this->listRecentTransactions($user);
            }

            // Fallback: show recent transactions
            return $this->listRecentTransactions($user);

        } catch (\Exception $e) {
            Log::error('Inspect handler error', [
                'user_id' => $user->id,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            return "Maaf, gagal mengambil data 😓 Coba lagi ya!";
        }
    }

    private function listRecentTransactions(User $user): string
    {
        $transactions = $this->transactionRepo->recent($user->id, 8);

        if ($transactions->isEmpty()) {
            return "Belum ada transaksi yang dicatat 📝";
        }

        $months = [
            'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr',
            'May' => 'Mei', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Agu',
            'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des'
        ];

        $header = sprintf(
            "%-23s | %-13s | %-6s | %-11s | %-9s | %s",
            "TRANSAKSI", "KATEGORI", "DOMPET", "TANGGAL", "JUMLAH", "STATUS"
        );

        $divider = "------------------------+---------------+--------+-------------+-----------+-------";

        $rows = [];
        foreach ($transactions as $tx) {
            $desc = mb_strimwidth($tx->description, 0, 23, '');
            $categoryName = $tx->category->name ?? 'Lainnya';
            $category = mb_strimwidth($categoryName, 0, 13, '');
            $walletName = $tx->wallet->name ?? 'Cash';
            $wallet = mb_strimwidth($walletName, 0, 6, '');

            $monthEng = $tx->transaction_date->format('M');
            $monthIndo = $months[$monthEng] ?? $monthEng;
            $date = $tx->transaction_date->format('j') . ' ' . $monthIndo . ' ' . $tx->transaction_date->format('Y');

            $sign = $tx->type->value === 'income' ? '+' : '-';
            $amount = $sign . 'Rp' . number_format($tx->amount, 0, ',', '.');

            $confidence = $tx->ai_confidence ? round($tx->ai_confidence * 100) : 95;
            $status = $confidence . '%';

            $rows[] = sprintf(
                "%-23s | %-13s | %-6s | %-11s | %-9s | %s",
                $desc, $category, $wallet, $date, $amount, $status
            );
        }

        return "```\n" . $header . "\n" . $divider . "\n" . implode("\n", $rows) . "\n```";
    }

    private function getWalletBalance(User $user, string $walletHint): string
    {
        $wallet = $user->wallets()
            ->where('name', 'LIKE', '%' . $walletHint . '%')
            ->first();

        if (!$wallet) {
            return "Wallet \"{$walletHint}\" tidak ditemukan 🤔 Ketik \"daftar wallet\" untuk melihat wallet kamu.";
        }

        $balance = number_format($wallet->balance, 0, ',', '.');
        return "💰 Saldo {$wallet->name}: Rp{$balance}";
    }

    private function getAllBalances(User $user): string
    {
        $wallets = $user->wallets()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($wallets->isEmpty()) {
            return "Belum ada wallet. Ketik \"buat wallet Cash\" untuk mulai!";
        }

        $lines = ["💰 Saldo semua wallet:\n"];
        $total = 0;

        foreach ($wallets as $wallet) {
            $balance = number_format($wallet->balance, 0, ',', '.');
            $default = $wallet->is_default ? ' ⭐' : '';
            $lines[] = "• {$wallet->name}: Rp{$balance}{$default}";
            $total += $wallet->balance;
        }

        $lines[] = "\n💵 Total: Rp" . number_format($total, 0, ',', '.');

        return implode("\n", $lines);
    }

    private function getTransactionDetail(User $user, string $hint): string
    {
        $candidates = $this->candidateResolver->resolve($user, ['description' => $hint]);

        if ($candidates->isEmpty()) {
            return "Transaksi \"{$hint}\" tidak ditemukan 🤔";
        }

        $tx = $candidates->first();
        $amount = number_format($tx->amount, 0, ',', '.');
        $type = $tx->type->value === 'income' ? 'Pemasukan' : 'Pengeluaran';
        $date = $tx->transaction_date->format('j M Y');
        $wallet = $tx->wallet->name ?? 'Unknown';
        $category = $tx->category->name ?? 'Unknown';
        $confidence = round($tx->ai_confidence * 100);

        $lines = [
            "📄 Detail transaksi:\n",
            "📝 {$tx->description}",
            "💰 Rp{$amount} ({$type})",
            "🏷️ Kategori: {$category}",
            "👛 Wallet: {$wallet}",
            "📅 Tanggal: {$date}",
            "🤖 Keyakinan AI: {$confidence}%",
        ];

        if ($tx->raw_input) {
            $lines[] = "💬 Input asli: \"{$tx->raw_input}\"";
        }

        return implode("\n", $lines);
    }

    private function getBudgetStatus(User $user, string $categoryHint): string
    {
        // Find category by hint
        $category = Category::forUser($user->id)
            ->where('name', 'LIKE', '%' . $categoryHint . '%')
            ->first();

        if (!$category) {
            return "Kategori \"{$categoryHint}\" tidak ditemukan 🤔";
        }

        $budget = Budget::where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->first();

        if (!$budget) {
            return "Belum ada budget untuk {$category->name}. Ketik \"buat budget {$category->name} [jumlah]\" untuk membuatnya!";
        }

        $limit = number_format($budget->amount, 0, ',', '.');
        $used = $budget->getUsedAmount();
        $usedFormatted = number_format($used, 0, ',', '.');
        $remaining = max(0, $budget->amount - $used);
        $remainingFormatted = number_format($remaining, 0, ',', '.');
        $percentage = round($budget->getUsagePercentage(), 1);

        $emoji = $percentage >= 90 ? '🔴' : ($percentage >= 70 ? '🟡' : '🟢');

        $lines = [
            "📊 Budget {$category->name}:\n",
            "💰 Limit: Rp{$limit}",
            "📤 Terpakai: Rp{$usedFormatted} ({$percentage}%)",
            "📥 Sisa: Rp{$remainingFormatted}",
            "{$emoji} " . $this->getProgressBar($percentage),
        ];

        if ($percentage >= 90) {
            $lines[] = "\n⚠️ Hati-hati, budget hampir habis!";
        }

        return implode("\n", $lines);
    }

    private function listWallets(User $user): string
    {
        return $this->getAllBalances($user);
    }

    private function listCategories(User $user): string
    {
        $categories = $user->availableCategories();

        if ($categories->isEmpty()) {
            return "Belum ada kategori.";
        }

        $expense = $categories->filter(fn($c) => $c->type->value === 'expense');
        $income = $categories->filter(fn($c) => $c->type->value === 'income');

        $lines = ["📋 Kategori kamu:\n"];

        if ($expense->isNotEmpty()) {
            $lines[] = "💸 Pengeluaran:";
            foreach ($expense as $cat) {
                $icon = $cat->icon ?? '📌';
                $custom = $cat->user_id ? ' (custom)' : '';
                $lines[] = "  {$icon} {$cat->name}{$custom}";
            }
        }

        if ($income->isNotEmpty()) {
            $lines[] = "\n💰 Pemasukan:";
            foreach ($income as $cat) {
                $icon = $cat->icon ?? '📌';
                $custom = $cat->user_id ? ' (custom)' : '';
                $lines[] = "  {$icon} {$cat->name}{$custom}";
            }
        }

        return implode("\n", $lines);
    }

    private function listBudgets(User $user): string
    {
        $budgets = $user->budgets()->with('category:id,name,icon')->get();

        if ($budgets->isEmpty()) {
            return "Belum ada budget yang diatur 📊 Ketik \"buat budget [kategori] [jumlah]\" untuk membuatnya!";
        }

        $lines = ["📊 Budget kamu:\n"];

        foreach ($budgets as $budget) {
            $limit = number_format($budget->amount, 0, ',', '.');
            $used = $budget->getUsedAmount();
            $percentage = round($budget->getUsagePercentage(), 1);
            $emoji = $percentage >= 90 ? '🔴' : ($percentage >= 70 ? '🟡' : '🟢');
            $categoryName = $budget->category->name ?? 'Unknown';

            $lines[] = "{$emoji} {$categoryName}: Rp" . number_format($used, 0, ',', '.') . " / Rp{$limit} ({$percentage}%)";
        }

        return implode("\n", $lines);
    }

    private function getProgressBar(float $percentage): string
    {
        $filled = (int) round($percentage / 10);
        $empty = 10 - $filled;
        return str_repeat('█', $filled) . str_repeat('░', $empty) . " {$percentage}%";
    }

    private function matchesKeywords(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }
        return false;
    }
}
