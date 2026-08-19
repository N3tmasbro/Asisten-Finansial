<?php

namespace App\Services\Chat;

use App\Contracts\AIProviderInterface;
use App\Models\Budget;
use App\Models\Category;
use App\Models\ChatMessage;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Transaction\CategoryMatcherService;
use Illuminate\Support\Facades\Log;

/**
 * Handles wallet, category, and budget management via WhatsApp.
 *
 * Supported actions:
 * - create_wallet, rename_wallet
 * - create_category, rename_category
 * - create_budget, update_budget, delete_budget
 */
class ManageRecordsHandlerService
{
    private const MAX_BUDGET_AMOUNT = 10_000_000_000; // Rp10 miliar
    private const MIN_BUDGET_AMOUNT = 1_000;          // Rp1.000

    /** E-wallet provider names for auto type inference */
    private const EWALLET_NAMES = ['gopay', 'ovo', 'dana', 'shopeepay', 'linkaja', 'sakuku', 'isaku', 'jenius'];
    /** Bank names for auto type inference */
    private const BANK_NAMES = ['bca', 'bni', 'bri', 'mandiri', 'cimb', 'permata', 'danamon', 'bsi', 'mega', 'btpn', 'ocbc', 'hsbc', 'jago', 'blu', 'seabank', 'neo'];

    public function __construct(
        private AIProviderInterface $aiProvider,
        private CategoryMatcherService $categoryMatcher,
    ) {}

    /**
     * Handle a manage records request.
     */
    public function handle(User $user, string $message, ChatMessage $chatMessage): string
    {
        try {
            $context = $this->buildContext($user);
            $command = $this->aiProvider->parseFinancialCommand($message, $context);

            return match ($command->action) {
                'create_wallet'                 => $this->createWallet($user, $command->data ?? []),
                'create_wallet_with_balance'    => $this->createWalletWithBalance($user, $command->data ?? []),
                'rename_wallet'                 => $this->renameWallet($user, $command->data ?? []),
                'delete_wallet'                 => $this->deleteWallet($user, $command->data ?? []),
                'set_wallet_balance'            => $this->setWalletBalance($user, $command->data ?? []),
                'set_multiple_wallet_balances'  => $this->setMultipleWalletBalances($user, $command->data ?? []),
                'create_category'               => $this->createCategory($user, $command->data ?? []),
                'rename_category'               => $this->renameCategory($user, $command->data ?? []),
                'create_budget'                 => $this->createOrUpdateBudget($user, $command->data ?? []),
                'create_multiple_budgets'       => $this->createMultipleBudgets($user, $command->data ?? []),
                'update_budget'                 => $this->createOrUpdateBudget($user, $command->data ?? []),
                'delete_budget'                 => $this->deleteBudget($user, $command->data ?? []),
                default => "Aku kurang paham perintahnya 🤔 Coba bilang misalnya:\n"
                    . "• \"buat wallet Dana\"\n"
                    . "• \"hapus wallet BCA\"\n"
                    . "• \"saldo cash 1jt\" atau \"BCA 3jt\"\n"
                    . "• \"cash 1jt, BCA 3jt\"\n"
                    . "• \"buat kategori Investasi\"\n"
                    . "• \"buat budget makan 800rb\"\n"
                    . "• \"budget makan 800rb sama transport 200rb\"",
            };

        } catch (\Exception $e) {
            Log::error('Manage records handler error', [
                'user_id' => $user->id,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            return "Maaf, gagal memproses 😓 Coba lagi ya!";
        }
    }

    // ─────────────────────────────────────────
    //  Wallet Management
    // ─────────────────────────────────────────

    private function createWallet(User $user, array $data): string
    {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            return "Nama wallet tidak boleh kosong. Coba: \"buat wallet Dana\"";
        }

        // Check duplicate
        $existing = $user->wallets()->where('name', 'LIKE', $name)->first();
        if ($existing) {
            return "Wallet \"{$existing->name}\" sudah ada 👛 Mau rename? Coba: \"rename wallet {$existing->name} jadi [nama baru]\"";
        }

        // Infer wallet type from name
        $type = $this->inferWalletType($name);

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => $type,
            'balance' => 0,
            'is_default' => !$user->wallets()->exists(),
        ]);

        $typeLabel = match ($type) {
            'bank' => 'Bank',
            'ewallet' => 'E-wallet',
            default => 'Cash',
        };

        return "Wallet \"{$wallet->name}\" berhasil dibuat sebagai {$typeLabel} ✅ Saldo awal: Rp0";
    }

    private function createWalletWithBalance(User $user, array $data): string
    {
        $name = trim($data['name'] ?? '');
        $amount = (int) ($data['amount'] ?? 0);

        if (empty($name)) {
            return "Nama wallet tidak boleh kosong. Coba: \"buat wallet BCA dengan saldo 5jt\"";
        }

        // Check duplicate
        $existing = $user->wallets()->where('name', 'LIKE', $name)->first();
        if ($existing) {
            // Wallet already exists — just update its balance instead
            $oldBalance = number_format($existing->balance, 0, ',', '.');
            $existing->update(['balance' => $amount]);
            $newBalance = number_format($amount, 0, ',', '.');
            return "Wallet \"{$existing->name}\" sudah ada. Saldo diupdate dari Rp{$oldBalance} → Rp{$newBalance} ✅";
        }

        $type = $this->inferWalletType($name);

        $wallet = Wallet::create([
            'user_id'    => $user->id,
            'name'       => $name,
            'type'       => $type,
            'balance'    => $amount,
            'is_default' => !$user->wallets()->exists(),
        ]);

        $typeLabel = match ($type) {
            'bank'    => 'Bank',
            'ewallet' => 'E-wallet',
            default   => 'Cash',
        };

        $formattedAmount = number_format($amount, 0, ',', '.');
        return "Wallet \"{$wallet->name}\" berhasil dibuat sebagai {$typeLabel} ✅ Saldo awal: Rp{$formattedAmount}";
    }

    private function setWalletBalance(User $user, array $data): string
    {
        $name   = trim($data['name'] ?? '');
        $amount = (int) ($data['amount'] ?? 0);

        if (empty($name)) {
            return "Nama wallet tidak disebutkan. Coba: \"saldo BCA 3jt\"";
        }

        // Fuzzy match wallet
        $wallet = $user->wallets()
            ->where('name', 'LIKE', '%' . $name . '%')
            ->first();

        if (!$wallet) {
            // Wallet not found — offer to create it
            $formattedAmount = number_format($amount, 0, ',', '.');
            return "Wallet \"{$name}\" belum ada 🤔\n"
                . "Mau aku buatkan? Coba: \"buat wallet {$name} dengan saldo {$formattedAmount}\"";
        }

        $oldBalance = number_format($wallet->balance, 0, ',', '.');
        $wallet->update(['balance' => $amount]);
        $newBalance = number_format($amount, 0, ',', '.');

        return "Saldo wallet \"{$wallet->name}\" diupdate: Rp{$oldBalance} → Rp{$newBalance} ✅";
    }

    private function setMultipleWalletBalances(User $user, array $data): string
    {
        $wallets = $data['wallets'] ?? [];

        if (empty($wallets) || !is_array($wallets)) {
            return "Format tidak terbaca 🤔 Coba: \"cash 1jt, BCA 3jt\"";
        }

        $results   = [];
        $notFound  = [];

        foreach ($wallets as $entry) {
            $name   = trim($entry['name'] ?? '');
            $amount = (int) ($entry['amount'] ?? 0);

            if (empty($name)) continue;

            $wallet = $user->wallets()
                ->where('name', 'LIKE', '%' . $name . '%')
                ->first();

            if (!$wallet) {
                // Auto-create wallet if not found
                $type   = $this->inferWalletType($name);
                $wallet = Wallet::create([
                    'user_id'    => $user->id,
                    'name'       => $name,
                    'type'       => $type,
                    'balance'    => $amount,
                    'is_default' => !$user->wallets()->exists(),
                ]);
                $formatted = number_format($amount, 0, ',', '.');
                $results[] = "✅ Wallet \"{$wallet->name}\" dibuat baru, saldo Rp{$formatted}";
            } else {
                $oldBalance = number_format($wallet->balance, 0, ',', '.');
                $wallet->update(['balance' => $amount]);
                $newBalance = number_format($amount, 0, ',', '.');
                $results[] = "✅ \"{$wallet->name}\": Rp{$oldBalance} → Rp{$newBalance}";
            }
        }

        if (empty($results)) {
            return "Tidak ada wallet yang berhasil diproses 🤔";
        }

        return implode("\n", $results);
    }

    private function renameWallet(User $user, array $data): string
    {
        $oldName = trim($data['old_name'] ?? '');
        $newName = trim($data['new_name'] ?? '');

        if (empty($oldName) || empty($newName)) {
            return "Coba format: \"rename wallet [nama lama] jadi [nama baru]\"";
        }

        $wallet = $user->wallets()
            ->where('name', 'LIKE', '%' . $oldName . '%')
            ->first();

        if (!$wallet) {
            return "Wallet \"{$oldName}\" tidak ditemukan 🤔";
        }

        // Check if new name already exists
        $duplicate = $user->wallets()
            ->where('id', '!=', $wallet->id)
            ->where('name', 'LIKE', $newName)
            ->first();

        if ($duplicate) {
            return "Wallet \"{$newName}\" sudah ada 🤔";
        }

        $oldDisplayName = $wallet->name;
        $wallet->update(['name' => $newName]);

        return "Wallet \"{$oldDisplayName}\" berhasil diubah menjadi \"{$newName}\" ✅";
    }

    private function deleteWallet(User $user, array $data): string
    {
        $name = trim($data['name'] ?? '');

        if (empty($name)) {
            return "Nama wallet tidak disebutkan. Coba: \"hapus wallet BCA\"";
        }

        $wallet = $user->wallets()
            ->where('name', 'LIKE', '%' . $name . '%')
            ->first();

        if (!$wallet) {
            return "Wallet \"{$name}\" tidak ditemukan 🤔";
        }

        // Prevent deleting the only wallet
        if ($user->wallets()->count() <= 1) {
            return "Tidak bisa menghapus wallet satu-satunya 🚫 Kamu butuh minimal 1 wallet.";
        }

        // If it's the default wallet, assign default to another wallet first
        if ($wallet->is_default) {
            $other = $user->wallets()->where('id', '!=', $wallet->id)->first();
            if ($other) {
                $other->update(['is_default' => true]);
            }
        }

        $walletName = $wallet->name;
        $wallet->delete();

        return "Wallet \"{$walletName}\" berhasil dihapus ✅";
    }

    private function inferWalletType(string $name): string
    {
        $nameLower = mb_strtolower($name);

        foreach (self::EWALLET_NAMES as $ewallet) {
            if (str_contains($nameLower, $ewallet)) {
                return 'ewallet';
            }
        }

        foreach (self::BANK_NAMES as $bank) {
            if (str_contains($nameLower, $bank)) {
                return 'bank';
            }
        }

        return 'cash';
    }

    // ─────────────────────────────────────────
    //  Category Management
    // ─────────────────────────────────────────

    private function createCategory(User $user, array $data): string
    {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            return "Nama kategori tidak boleh kosong. Coba: \"buat kategori Investasi\"";
        }

        $type = ($data['type'] ?? 'expense') === 'income' ? 'income' : 'expense';

        // Check duplicate (including defaults)
        $existing = Category::forUser($user->id)
            ->where('name', $name)
            ->where('type', $type)
            ->first();

        if ($existing) {
            return "Kategori \"{$existing->name}\" sudah ada 🏷️";
        }

        Category::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => $type,
            'icon' => '📌',
            'is_default' => false,
            'sort_order' => 50,
        ]);

        $typeLabel = $type === 'income' ? 'pemasukan' : 'pengeluaran';
        return "Kategori \"{$name}\" ({$typeLabel}) berhasil dibuat ✅";
    }

    private function renameCategory(User $user, array $data): string
    {
        $oldName = trim($data['old_name'] ?? '');
        $newName = trim($data['new_name'] ?? '');

        if (empty($oldName) || empty($newName)) {
            return "Coba format: \"rename kategori [nama lama] jadi [nama baru]\"";
        }

        // Can only rename user-owned categories
        $category = Category::where('user_id', $user->id)
            ->where('name', 'LIKE', '%' . $oldName . '%')
            ->first();

        if (!$category) {
            // Check if it's a default category
            $isDefault = Category::whereNull('user_id')
                ->where('name', 'LIKE', '%' . $oldName . '%')
                ->exists();

            if ($isDefault) {
                return "Kategori default tidak bisa diubah namanya 🚫";
            }

            return "Kategori \"{$oldName}\" tidak ditemukan 🤔";
        }

        $oldDisplayName = $category->name;
        $category->update(['name' => $newName]);

        return "Kategori \"{$oldDisplayName}\" berhasil diubah menjadi \"{$newName}\" ✅";
    }

    // ─────────────────────────────────────────
    //  Budget Management
    // ─────────────────────────────────────────

    private function createOrUpdateBudget(User $user, array $data): string
    {
        $categoryHint = $data['category'] ?? '';
        $amount       = (int) ($data['amount'] ?? 0);
        $periodType   = ($data['period'] ?? 'monthly') === 'weekly' ? 'weekly' : 'monthly';

        if (empty($categoryHint)) {
            return "Kategori budget belum disebut. Coba: \"buat budget makan 2 juta\"";
        }

        if ($amount <= 0) {
            return "Nominal budget belum disebut. Coba: \"buat budget {$categoryHint} 2 juta\"";
        }

        if ($amount < self::MIN_BUDGET_AMOUNT) {
            return "Nominal budget terlalu kecil. Minimum Rp" . number_format(self::MIN_BUDGET_AMOUNT, 0, ',', '.');
        }

        if ($amount > self::MAX_BUDGET_AMOUNT) {
            return "Nominal budget terlalu besar. Maksimum Rp" . number_format(self::MAX_BUDGET_AMOUNT, 0, ',', '.');
        }

        // Resolve category with fuzzy slang matching
        $category = $this->resolveCategoryForBudget($user, $categoryHint);

        if (!$category) {
            return "Kategori \"{$categoryHint}\" tidak ditemukan 🤔 Ketik \"daftar kategori\" untuk melihat.";
        }

        // Check existing budget (dedup: update instead of create)
        $existing = Budget::where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->where('period_type', $periodType)
            ->first();

        $formattedAmount = number_format($amount, 0, ',', '.');

        if ($existing) {
            $oldAmount = number_format($existing->amount, 0, ',', '.');
            $existing->update(['amount' => $amount]);
            return "Budget {$category->name} diupdate dari Rp{$oldAmount} → Rp{$formattedAmount} ✅";
        }

        Budget::create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'amount'      => $amount,
            'period_type' => $periodType,
        ]);

        $periodLabel = $periodType === 'weekly' ? 'per minggu' : 'per bulan';
        return "Budget {$category->name} Rp{$formattedAmount} {$periodLabel} berhasil dibuat ✅";
    }

    private function createMultipleBudgets(User $user, array $data): string
    {
        $budgets = $data['budgets'] ?? [];

        if (empty($budgets) || !is_array($budgets)) {
            return "Format tidak terbaca 🤔 Coba: \"budget makan 800rb sama transport 200rb\"";
        }

        $results = [];
        $failed  = [];

        foreach ($budgets as $entry) {
            $categoryHint = $entry['category'] ?? '';
            $amount       = (int) ($entry['amount'] ?? 0);
            $periodType   = ($entry['period'] ?? 'monthly') === 'weekly' ? 'weekly' : 'monthly';

            if (empty($categoryHint) || $amount <= 0) {
                continue;
            }

            $category = $this->resolveCategoryForBudget($user, $categoryHint);

            if (!$category) {
                $failed[] = "\"{$categoryHint}\" tidak ditemukan";
                continue;
            }

            $existing = Budget::where('user_id', $user->id)
                ->where('category_id', $category->id)
                ->where('period_type', $periodType)
                ->first();

            $formattedAmount = number_format($amount, 0, ',', '.');

            if ($existing) {
                $oldAmount = number_format($existing->amount, 0, ',', '.');
                $existing->update(['amount' => $amount]);
                $results[] = "✅ Budget {$category->name}: Rp{$oldAmount} → Rp{$formattedAmount}";
            } else {
                Budget::create([
                    'user_id'     => $user->id,
                    'category_id' => $category->id,
                    'amount'      => $amount,
                    'period_type' => $periodType,
                ]);
                $results[] = "✅ Budget {$category->name}: Rp{$formattedAmount}/bulan";
            }
        }

        if (empty($results)) {
            $msg = "Tidak ada budget yang berhasil dibuat 🤔";
            if (!empty($failed)) {
                $msg .= "\nKategori tidak ditemukan: " . implode(', ', $failed);
            }
            return $msg;
        }

        $response = implode("\n", $results);

        if (!empty($failed)) {
            $response .= "\n\n⚠️ Kategori tidak ditemukan: " . implode(', ', $failed);
        }

        return $response;
    }

    /**
     * Resolve category by name with fuzzy + slang matching.
     * Supports: "konsumsi" → Makan & Minum, "bensin" → Transport, etc.
     */
    private function resolveCategoryForBudget(User $user, string $hint): ?Category
    {
        // Slang/abbreviation map to canonical category names
        $slangMap = [
            'konsumsi'   => 'Makan & Minum',
            'makan'      => 'Makan & Minum',
            'minum'      => 'Makan & Minum',
            'food'       => 'Makan & Minum',
            'bensin'     => 'Transport',
            'bbm'        => 'Transport',
            'transportasi' => 'Transport',
            'ojek'       => 'Transport',
            'grab'       => 'Transport',
            'gojek'      => 'Transport',
            'listrik'    => 'Tagihan',
            'wifi'       => 'Tagihan',
            'internet'   => 'Tagihan',
            'pulsa'      => 'Tagihan',
            'hiburan'    => 'Hiburan',
            'game'       => 'Hiburan',
            'nonton'     => 'Hiburan',
            'streaming'  => 'Hiburan',
            'obat'       => 'Kesehatan',
            'dokter'     => 'Kesehatan',
            'kursus'     => 'Pendidikan',
            'sekolah'    => 'Pendidikan',
            'kuliah'     => 'Pendidikan',
            'belanja'    => 'Belanja',
            'shop'       => 'Belanja',
        ];

        $hintLower = mb_strtolower(trim($hint));

        // Check slang map first
        foreach ($slangMap as $slang => $canonical) {
            if (str_contains($hintLower, $slang)) {
                $category = Category::forUser($user->id)
                    ->where('name', $canonical)
                    ->where('type', 'expense')
                    ->first();
                if ($category) return $category;
            }
        }

        // Fuzzy LIKE match
        return Category::forUser($user->id)
            ->where('name', 'LIKE', '%' . $hint . '%')
            ->where('type', 'expense')
            ->first();
    }

    private function deleteBudget(User $user, array $data): string
    {
        $categoryHint = $data['category'] ?? '';

        if (empty($categoryHint)) {
            return "Kategori budget belum disebut. Coba: \"hapus budget makan\"";
        }

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
            return "Budget untuk {$category->name} tidak ditemukan.";
        }

        $budget->delete();
        return "Budget {$category->name} berhasil dihapus ✅";
    }

    private function buildContext(User $user): array
    {
        return [
            'wallets' => $user->wallets()->pluck('name')->toArray(),
            'categories_expense' => $this->categoryMatcher->getCategoryNamesForUser($user->id, 'expense'),
            'categories_income' => $this->categoryMatcher->getCategoryNamesForUser($user->id, 'income'),
        ];
    }
}
