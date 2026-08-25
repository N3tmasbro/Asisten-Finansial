<?php

namespace App\Services\Chat;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Handles read-only inspection requests via WhatsApp.
 *
 * Uses lightweight keyword & regex matching + date/limit parsing.
 * All returned data comes from the database.
 *
 * Supported:
 * - Recent / filtered transactions (by date range, limit 10/20/all, etc.)
 * - Wallet balance (single or all)
 * - Transaction detail
 * - Budget status
 * - List wallets
 * - List categories
 * - List budgets
 */
class InspectHandlerService
{
    private const WALLET_KEYWORDS = [
        'cek dompet', 'lihat dompet', 'tampil dompet', 'show dompet',
        'dompet saya', 'dompet ku', 'dompetku', 'dompetnya',
        'cek wallet', 'lihat wallet', 'tampil wallet',
        'daftar wallet', 'list wallet', 'wallet apa aja', 'wallet saya', 'wallet ku', 'semua wallet',
        'saldo semua', 'saldo total', 'semua saldo', 'total saldo',
        'berapa saldo', 'saldo gue', 'saldo gw', 'saldo aku',
    ];

    private const BUDGET_KEYWORDS = [
        'cek budget', 'lihat budget', 'tampil budget', 'show budget',
        'budget saya', 'budget ku', 'budgetku', 'anggaran saya',
        'daftar budget', 'list budget', 'budget apa aja', 'semua budget',
        'cek anggaran', 'lihat anggaran', 'sisa budget', 'sis budget',
        'sisa anggaran', 'sis anggaran', 'sisa limit', 'sisa budgetku',
        'sisa budget saya', 'info budget', 'status budget',
    ];

    private const CATEGORY_KEYWORDS = [
        'cek kategori', 'lihat kategori', 'tampil kategori',
        'daftar kategori', 'list kategori', 'kategori apa aja', 'kategori saya', 'semua kategori',
    ];

    public function __construct(
        private TransactionRepository $transactionRepo,
        private TransactionCandidateResolver $candidateResolver,
    ) {}

    /**
     * Handle an inspection request.
     */
    public function handle(User $user, string $message): string
    {
        $msg = mb_strtolower(trim($message));

        try {
            // ── Budget (sisa budget / sis budget / budget specific or all) ──
            if ($this->matchesKeywords($msg, self::BUDGET_KEYWORDS) || str_contains($msg, 'budget') || str_contains($msg, 'anggaran')) {
                // Check if specific category was requested, e.g. "budget makanan"
                if (preg_match('/(?:cek\s+|lihat\s+|sisa\s+|sis\s+)?budget\s+([a-zA-Z0-9\s]+?)(?:\s+bulan\s+ini)?$/i', $msg, $matches)) {
                    $categoryHint = trim($matches[1]);
                    $skip = ['apa', 'berapa', 'gimana', 'saya', 'ku', 'aku', 'semua', 'list', 'daftar', 'sisa', 'sis', 'total', 'info', 'status'];
                    if (!in_array($categoryHint, $skip) && strlen($categoryHint) > 2) {
                        return $this->getBudgetStatus($user, $categoryHint);
                    }
                }
                return $this->listBudgets($user);
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

            // ── Transactions (recent / filtered by range or limit) ─────────
            return $this->listRecentTransactions($user, $msg);

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
    //  Transaction List Handler (with Date-Range & Limit support)
    // ─────────────────────────────────────────────────────────────────────────

    private function listRecentTransactions(User $user, string $msg = ''): string
    {
        $limit     = $this->parseLimit($msg);
        $dateRange = $this->parseDateRange($msg);

        $query = Transaction::where('user_id', $user->id)
            ->with(['category:id,name,icon', 'wallet:id,name']);

        if ($dateRange) {
            $query->whereBetween('transaction_date', [$dateRange['from'], $dateRange['to']]);
        }

        $transactions = $query->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        if ($transactions->isEmpty()) {
            if ($dateRange) {
                return "Tidak ada transaksi pada periode *{$dateRange['label']}* 📝";
            }
            return "Belum ada transaksi yang dicatat 📝\n\nCoba catat dulu, misalnya:\n• _makan siang 35rb_\n• _terima gaji 8jt_";
        }

        $months = [
            'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr',
            'May' => 'Mei', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Agu',
            'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des',
        ];

        // Build header title
        if ($dateRange) {
            $title = "📋 *Transaksi ({$dateRange['label']})*";
        } elseif ($limit === 100) {
            $title = "📋 *Semua Transaksi*";
        } elseif ($limit !== 7) {
            $title = "📋 *{$limit} Transaksi Terakhir*";
        } else {
            $title = "📋 *Transaksi Terakhir*";
        }

        $lines = [$title . "\n"];

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
        }

        // Options footer requested by user
        $lines[] = "〰️〰️〰️";
        $lines[] = "💡 *Opsi lihat transaksi:*";
        $lines[] = "  _transaksi 10-20 agustus_";
        $lines[] = "  _tampilkan 10 transaksi_";
        $lines[] = "  _tampilkan 20 transaksi_";
        $lines[] = "  _semua transaksi_";

        return rtrim(implode("\n", $lines));
    }

    /**
     * Parse date range from message.
     */
    private function parseDateRange(string $msg): ?array
    {
        $msg = mb_strtolower(trim($msg));
        $year = (int) date('Y');

        $monthMap = [
            'jan' => 1, 'januari' => 1,
            'feb' => 2, 'februari' => 2,
            'mar' => 3, 'maret' => 3,
            'apr' => 4, 'april' => 4,
            'mei' => 5, 'may' => 5,
            'jun' => 6, 'juni' => 6,
            'jul' => 7, 'juli' => 7,
            'agu' => 8, 'agustus' => 8, 'aug' => 8, 'agust' => 8,
            'sep' => 9, 'september' => 9, 'sept' => 9,
            'okt' => 10, 'oktober' => 10, 'oct' => 10,
            'nov' => 11, 'november' => 11,
            'des' => 12, 'desember' => 12, 'dec' => 12,
        ];

        // 1. Pattern: "10-20 agustus" or "10 - 20 agustus 2026"
        if (preg_match('/(\d{1,2})\s*[-–—]\s*(\d{1,2})\s+([a-zA-Z]+)(?:\s+(\d{4}))?/i', $msg, $m)) {
            $mNum = $monthMap[mb_strtolower($m[3])] ?? null;
            if ($mNum) {
                $y = isset($m[4]) ? (int) $m[4] : $year;
                $from = sprintf('%04d-%02d-%02d', $y, $mNum, (int) $m[1]);
                $to   = sprintf('%04d-%02d-%02d', $y, $mNum, (int) $m[2]);
                $monthName = ucfirst($m[3]);
                return ['from' => $from, 'to' => $to, 'label' => "{$m[1]} - {$m[2]} {$monthName}"];
            }
        }

        // 2. Pattern: "10 agustus sampai 20 agustus" or "10 agustus s/d 20 agustus"
        if (preg_match('/(\d{1,2})\s+([a-zA-Z]+)\s+(?:sampai|ke|s\/d|hingga|-)\s+(\d{1,2})\s+([a-zA-Z]+)(?:\s+(\d{4}))?/i', $msg, $m)) {
            $m1 = $monthMap[mb_strtolower($m[2])] ?? null;
            $m2 = $monthMap[mb_strtolower($m[4])] ?? null;
            if ($m1 && $m2) {
                $y = isset($m[5]) ? (int) $m[5] : $year;
                $from = sprintf('%04d-%02d-%02d', $y, $m1, (int) $m[1]);
                $to   = sprintf('%04d-%02d-%02d', $y, $m2, (int) $m[3]);
                $monthName1 = ucfirst($m[2]);
                $monthName2 = ucfirst($m[4]);
                return ['from' => $from, 'to' => $to, 'label' => "{$m[1]} {$monthName1} - {$m[3]} {$monthName2}"];
            }
        }

        // 3. Predefined periods
        if (str_contains($msg, 'bulan ini')) {
            return ['from' => date('Y-m-01'), 'to' => date('Y-m-d'), 'label' => 'Bulan Ini'];
        }
        if (str_contains($msg, 'bulan lalu')) {
            return [
                'from' => date('Y-m-01', strtotime('first day of last month')),
                'to'   => date('Y-m-t', strtotime('last month')),
                'label' => 'Bulan Lalu',
            ];
        }
        if (str_contains($msg, 'minggu ini')) {
            return ['from' => date('Y-m-d', strtotime('monday this week')), 'to' => date('Y-m-d'), 'label' => 'Minggu Ini'];
        }
        if (str_contains($msg, 'kemarin')) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            return ['from' => $yesterday, 'to' => $yesterday, 'label' => 'Kemarin'];
        }
        if (str_contains($msg, 'hari ini')) {
            $today = date('Y-m-d');
            return ['from' => $today, 'to' => $today, 'label' => 'Hari Ini'];
        }

        return null;
    }

    /**
     * Parse limit from message.
     */
    private function parseLimit(string $msg): int
    {
        $msg = mb_strtolower(trim($msg));
        if (str_contains($msg, 'semua')) {
            return 100;
        }
        if (preg_match('/(\d{1,3})\s*(?:transaksi|riwayat|data)?/i', $msg, $m)) {
            $val = (int) $m[1];
            if ($val > 0 && $val <= 100) {
                return $val;
            }
        }
        return 7;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Other Formatters
    // ─────────────────────────────────────────────────────────────────────────

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
