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
    // ─── Keyword groups — any match triggers the handler ──────────────────────

    private const WALLET_KEYWORDS = [
        // explicit dompet/wallet
        'cek dompet', 'lihat dompet', 'tampil dompet', 'show dompet',
        'dompet saya', 'dompet ku', 'dompetku', 'dompetnya',
        'cek wallet', 'lihat wallet', 'tampil wallet',
        'daftar wallet', 'list wallet', 'wallet apa aja', 'wallet saya', 'wallet ku', 'semua wallet',
        // saldo umbrella terms
        'saldo semua', 'saldo total', 'semua saldo', 'total saldo',
        'berapa saldo', 'saldo gue', 'saldo gw', 'saldo aku',
    ];

    private const BUDGET_KEYWORDS = [
        'cek budget', 'lihat budget', 'tampil budget', 'show budget',
        'budget saya', 'budget ku', 'budgetku', 'anggaran saya',
        'daftar budget', 'list budget', 'budget apa aja', 'semua budget',
        'cek anggaran', 'lihat anggaran',
    ];

    private const CATEGORY_KEYWORDS = [
        'cek kategori', 'lihat kategori', 'tampil kategori',
        'daftar kategori', 'list kategori', 'kategori apa aja', 'kategori saya', 'semua kategori',
    ];

    private const TRANSACTION_KEYWORDS = [
        'cek transaksi', 'lihat transaksi', 'tampil transaksi',
        'transaksi saya', 'transaksi ku', 'transaksi terbaru', 'transaksi terakhir',
        'riwayat', 'history', 'transaksi hari ini', 'apa aja yang dicatat',
        'cek riwayat', 'lihat riwayat',
    ];

    public function __construct(
        private TransactionRepository $transactionRepo,
        private TransactionCandidateResolver $candidateResolver,
    ) {}

    /**
     * Handle an inspection request. Uses keyword matching — no AI call.
     */
    public function handle(User $user, string $message): string
    {
        $msg = mb_strtolower(trim($message));

        try {
            // ── Budget (specific category) — check before generic budget ──
            // e.g. "budget makan", "cek budget makanan bulan ini"
            if (preg_match('/(?:cek\s+|lihat\s+)?budget\s+([a-zA-Z0-9\s]+?)(?:\s+bulan\s+ini)?$/i', $msg, $matches)) {
                $categoryHint = trim($matches[1]);
                $skip = ['apa', 'berapa', 'gimana', 'saya', 'ku', 'aku', 'semua', 'list', 'daftar'];
                if (!in_array($categoryHint, $skip) && strlen($categoryHint) > 2) {
                    return $this->getBudgetStatus($user, $categoryHint);
                }
            }

            // ── Wallets / Dompet ──────────────────────────────────────────
            if ($this->matchesKeywords($msg, self::WALLET_KEYWORDS)) {
                return $this->getAllBalances($user);
            }

            // Specific wallet saldo: "saldo BCA", "saldo gopay"
            if (preg_match('/saldo\s+(.+)/i', $msg, $matches)) {
                $walletHint = trim($matches[1]);
                $generic = ['berapa', 'semua', 'total', 'aku', 'ku', 'saya', 'gue', 'gw'];
                if (!in_array($walletHint, $generic)) {
                    return $this->getWalletBalance($user, $walletHint);
                }
                return $this->getAllBalances($user);
            }

            // ── Budget (all) ──────────────────────────────────────────────
            if ($this->matchesKeywords($msg, self::BUDGET_KEYWORDS)) {
                return $this->listBudgets($user);
            }

            // ── Categories ───────────────────────────────────────────────
            if ($this->matchesKeywords($msg, self::CATEGORY_KEYWORDS)) {
                return $this->listCategories($user);
            }

            // ── Transaction detail ────────────────────────────────────────
            if ($this->matchesKeywords($msg, ['detail transaksi', 'detail yang'])) {
                $hint = preg_replace('/^(detail\s+(?:transaksi\s+)?(?:yang\s+)?)/i', '', $msg);
                return $this->getTransactionDetail($user, trim($hint));
            }

            // ── Recent Transactions (explicit or fallback) ────────────────
            return $this->listRecentTransactions($user);

        } catch (\Exception $e) {
            Log::error('Inspect handler error', [
                'user_id' => $user->id,
                'message' => $message,
                'error'   => $e->getMessage(),
            ]);

            return "Maaf, gagal mengambil data 😓 Coba lagi ya!";
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Formatters — Mobile-friendly (NO fixed-width ASCII table)
    // ─────────────────────────────────────────────────────────────────────────

    private function listRecentTransactions(User $user): string
    {
        $transactions = $this->transactionRepo->recent($user->id, 7);

        if ($transactions->isEmpty()) {
            return "Belum ada transaksi yang dicatat 📝\n\nCoba catat dulu, misalnya:\n• _makan siang 35rb_\n• _terima gaji 8jt_";
        }

        $months = [
            'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr',
            'May' => 'Mei', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Agu',
            'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des',
        ];

        $lines = ["📋 *Transaksi Terakhir*\n"];

        $index = 1;
        foreach ($transactions as $tx) {
            $isIncome = $tx->type->value === 'income';
            $sign     = $isIncome ? '➕' : '➖';
            $amount   = 'Rp' . number_format($tx->amount, 0, ',', '.');
            $category = $tx->category->name ?? 'Lainnya';
            $wallet   = $tx->wallet->name ?? 'Cash';
            $monthEng = $tx->transaction_date->format('M');
            $date     = $tx->transaction_date->format('j') . ' ' . ($months[$monthEng] ?? $monthEng);
            $desc     = $tx->description;

            $lines[] = "{$sign} *{$desc}*";
            $lines[] = "   {$amount} • {$wallet} • {$date}";
            $lines[] = "   🏷️ {$category}";
            $lines[] = "";

            if ($index++ >= 7) break;
        }

        return rtrim(implode("\n", $lines));
    }

    private function getWalletBalance(User $user, string $walletHint): string
    {
        $wallet = $user->wallets()
            ->where('name', 'LIKE', '%' . $walletHint . '%')
            ->first();

        if (!$wallet) {
            return "Wallet \"{$walletHint}\" tidak ditemukan 🤔\n\nKetik *cek dompet* untuk melihat semua dompetmu.";
        }

        $balance = number_format($wallet->balance, 0, ',', '.');
        return "💰 *{$wallet->name}*\nSaldo: Rp{$balance}";
    }

    private function getAllBalances(User $user): string
    {
        $wallets = $user->wallets()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($wallets->isEmpty()) {
            return "Belum ada dompet 💼\n\nBuat dompet dulu:\n• _buat wallet Cash_\n• _buat wallet BCA_";
        }

        $lines = ["💼 *Dompet & Saldo*\n"];
        $total = 0;

        foreach ($wallets as $wallet) {
            $balance = number_format($wallet->balance, 0, ',', '.');
            $star    = $wallet->is_default ? ' ⭐' : '';
            $lines[] = "👛 *{$wallet->name}*{$star}: Rp{$balance}";
            $total  += $wallet->balance;
        }

        $lines[] = "";
        $lines[] = "💵 *Total: Rp" . number_format($total, 0, ',', '.') . "*";

        return implode("\n", $lines);
    }

    private function getTransactionDetail(User $user, string $hint): string
    {
        $candidates = $this->candidateResolver->resolve($user, ['description' => $hint]);

        if ($candidates->isEmpty()) {
            return "Transaksi \"{$hint}\" tidak ditemukan 🤔";
        }

        $tx         = $candidates->first();
        $amount     = number_format($tx->amount, 0, ',', '.');
        $type       = $tx->type->value === 'income' ? 'Pemasukan' : 'Pengeluaran';
        $date       = $tx->transaction_date->format('j M Y');
        $wallet     = $tx->wallet->name ?? 'Unknown';
        $category   = $tx->category->name ?? 'Unknown';
        $confidence = round($tx->ai_confidence * 100);

        $lines = [
            "📄 *Detail Transaksi*\n",
            "📝 {$tx->description}",
            "💰 Rp{$amount} ({$type})",
            "🏷️ Kategori: {$category}",
            "👛 Dompet: {$wallet}",
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
        $category = Category::forUser($user->id)
            ->where('name', 'LIKE', '%' . $categoryHint . '%')
            ->first();

        if (!$category) {
            return "Kategori \"{$categoryHint}\" tidak ditemukan 🤔\n\nKetik *cek kategori* untuk melihat semua kategorimu.";
        }

        $budget = Budget::where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->first();

        if (!$budget) {
            return "Belum ada budget untuk *{$category->name}* 📊\n\nBuat budget:\n_buat budget {$category->name} [jumlah]_";
        }

        $limit      = number_format($budget->amount, 0, ',', '.');
        $used       = $budget->getUsedAmount();
        $usedFmt    = number_format($used, 0, ',', '.');
        $remaining  = max(0, $budget->amount - $used);
        $remainFmt  = number_format($remaining, 0, ',', '.');
        $percentage = round($budget->getUsagePercentage(), 1);
        $bar        = $this->getProgressBar($percentage);
        $emoji      = $percentage >= 90 ? '🔴' : ($percentage >= 70 ? '🟡' : '🟢');

        $lines = [
            "📊 *Budget {$category->name}*\n",
            "{$emoji} {$bar}",
            "",
            "💰 Limit  : Rp{$limit}",
            "📤 Terpakai: Rp{$usedFmt} ({$percentage}%)",
            "📥 Sisa   : Rp{$remainFmt}",
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
        $income  = $categories->filter(fn($c) => $c->type->value === 'income');

        $lines = ["📋 *Kategori Kamu*\n"];

        if ($expense->isNotEmpty()) {
            $lines[] = "💸 *Pengeluaran:*";
            foreach ($expense as $cat) {
                $icon   = $cat->icon ?? '📌';
                $custom = $cat->user_id ? ' _(custom)_' : '';
                $lines[] = "  {$icon} {$cat->name}{$custom}";
            }
        }

        if ($income->isNotEmpty()) {
            $lines[] = "\n💰 *Pemasukan:*";
            foreach ($income as $cat) {
                $icon   = $cat->icon ?? '📌';
                $custom = $cat->user_id ? ' _(custom)_' : '';
                $lines[] = "  {$icon} {$cat->name}{$custom}";
            }
        }

        return implode("\n", $lines);
    }

    private function listBudgets(User $user): string
    {
        $budgets = $user->budgets()->with('category:id,name,icon')->get();

        if ($budgets->isEmpty()) {
            return "Belum ada budget yang diatur 📊\n\nBuat budget:\n_buat budget Makanan 500000_";
        }

        $lines = ["📊 *Budget Bulan Ini*\n"];

        foreach ($budgets as $budget) {
            $used       = $budget->getUsedAmount();
            $percentage = round($budget->getUsagePercentage(), 1);
            $emoji      = $percentage >= 90 ? '🔴' : ($percentage >= 70 ? '🟡' : '🟢');
            $catName    = $budget->category->name ?? 'Unknown';
            $limit      = number_format($budget->amount, 0, ',', '.');
            $usedFmt    = number_format($used, 0, ',', '.');

            $lines[] = "{$emoji} *{$catName}*";
            $lines[] = "   Rp{$usedFmt} / Rp{$limit} ({$percentage}%)";
            $lines[] = "   " . $this->getProgressBar($percentage);
            $lines[] = "";
        }

        return rtrim(implode("\n", $lines));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getProgressBar(float $percentage): string
    {
        $filled = (int) round($percentage / 10);
        $empty  = 10 - $filled;
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
