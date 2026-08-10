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
                'create_wallet' => $this->createWallet($user, $command->data ?? []),
                'rename_wallet' => $this->renameWallet($user, $command->data ?? []),
                'create_category' => $this->createCategory($user, $command->data ?? []),
                'rename_category' => $this->renameCategory($user, $command->data ?? []),
                'create_budget' => $this->createOrUpdateBudget($user, $command->data ?? []),
                'update_budget' => $this->createOrUpdateBudget($user, $command->data ?? []),
                'delete_budget' => $this->deleteBudget($user, $command->data ?? []),
                default => "Aku kurang paham perintahnya 🤔 Coba bilang misalnya:\n"
                    . "• \"buat wallet Dana\"\n"
                    . "• \"buat kategori Investasi\"\n"
                    . "• \"buat budget makan 2 juta\"",
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
        $amount = (int) ($data['amount'] ?? 0);
        $periodType = ($data['period'] ?? 'monthly') === 'weekly' ? 'weekly' : 'monthly';

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

        // Resolve category
        $category = Category::forUser($user->id)
            ->where('name', 'LIKE', '%' . $categoryHint . '%')
            ->where('type', 'expense')
            ->first();

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
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'period_type' => $periodType,
        ]);

        $periodLabel = $periodType === 'weekly' ? 'per minggu' : 'per bulan';
        return "Budget {$category->name} Rp{$formattedAmount} {$periodLabel} berhasil dibuat ✅";
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
