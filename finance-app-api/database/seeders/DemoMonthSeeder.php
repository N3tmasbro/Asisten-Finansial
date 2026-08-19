<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoMonthSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'senkusanjo@gmail.com')->first();
        if (!$user) {
            $user = User::first();
        }

        if (!$user) {
            return;
        }

        $userId = $user->id;

        // Clear previous transactions and budgets for this user
        Transaction::where('user_id', $userId)->forceDelete();
        Budget::where('user_id', $userId)->delete();

        // ── 1. Ensure Wallets ──
        $cash = Wallet::firstOrCreate(
            ['user_id' => $userId, 'name' => 'Cash'],
            ['type' => 'cash', 'balance' => 0, 'is_default' => true]
        );

        $bca = Wallet::firstOrCreate(
            ['user_id' => $userId, 'name' => 'BCA'],
            ['type' => 'bank', 'balance' => 0, 'is_default' => false]
        );

        $gopay = Wallet::firstOrCreate(
            ['user_id' => $userId, 'name' => 'GoPay'],
            ['type' => 'ewallet', 'balance' => 0, 'is_default' => false]
        );

        // Delete 'pegangan' if empty or unused
        Wallet::where('user_id', $userId)->where('name', 'pegangan')->delete();

        // ── 2. Get Category IDs ──
        $getCat = fn(string $name, string $type) => Category::forUser($userId)
            ->where('name', $name)
            ->where('type', $type)
            ->first()?->id;

        $catMakan     = $getCat('Makan & Minum', 'expense');
        $catTransport = $getCat('Transport', 'expense');
        $catBelanja   = $getCat('Belanja', 'expense');
        $catTagihan   = $getCat('Tagihan', 'expense');
        $catHiburan   = $getCat('Hiburan', 'expense');
        $catKesehatan = $getCat('Kesehatan', 'expense');
        $catLainnyaEx = $getCat('Lainnya', 'expense');

        $catGaji      = $getCat('Gaji', 'income');
        $catSampingan = $getCat('Freelance/Sampingan', 'income');
        $catBonus     = $getCat('Bonus/THR', 'income');

        // ── 3. Create Budgets ──
        if ($catMakan)     Budget::create(['user_id' => $userId, 'category_id' => $catMakan, 'amount' => 1500000, 'period_type' => 'monthly']);
        if ($catTransport) Budget::create(['user_id' => $userId, 'category_id' => $catTransport, 'amount' => 500000, 'period_type' => 'monthly']);
        if ($catBelanja)   Budget::create(['user_id' => $userId, 'category_id' => $catBelanja, 'amount' => 1000000, 'period_type' => 'monthly']);
        if ($catHiburan)   Budget::create(['user_id' => $userId, 'category_id' => $catHiburan, 'amount' => 400000, 'period_type' => 'monthly']);
        if ($catTagihan)   Budget::create(['user_id' => $userId, 'category_id' => $catTagihan, 'amount' => 600000, 'period_type' => 'monthly']);

        // ── 4. Generate 30-day Transactions (July 21 to August 19, 2026) ──
        $transactionsData = [
            // --- Income ---
            ['date' => '2026-07-25', 'type' => 'income', 'amount' => 7500000, 'desc' => 'Gaji Bulanan Juli', 'cat' => $catGaji, 'wallet' => $bca->id],
            ['date' => '2026-07-26', 'type' => 'income', 'amount' => 800000,  'desc' => 'Tarik Cash Mandiri', 'cat' => $catBonus, 'wallet' => $cash->id],
            ['date' => '2026-08-05', 'type' => 'income', 'amount' => 1500000, 'desc' => 'Project Freelance UI Design', 'cat' => $catSampingan, 'wallet' => $bca->id],
            ['date' => '2026-08-10', 'type' => 'income', 'amount' => 500000,  'desc' => 'Bonus Performa', 'cat' => $catBonus, 'wallet' => $gopay->id],

            // --- Expenses (Spread over 30 days) ---
            // Week 1 (July 21 - July 27)
            ['date' => '2026-07-21', 'type' => 'expense', 'amount' => 35000,  'desc' => 'Makan Padang Siang', 'cat' => $catMakan, 'wallet' => $cash->id],
            ['date' => '2026-07-21', 'type' => 'expense', 'amount' => 100000, 'desc' => 'Isi Bensin Pertamax', 'cat' => $catTransport, 'wallet' => $bca->id],
            ['date' => '2026-07-22', 'type' => 'expense', 'amount' => 25000,  'desc' => 'Kopi Janji Jiwa', 'cat' => $catMakan, 'wallet' => $gopay->id],
            ['date' => '2026-07-23', 'type' => 'expense', 'amount' => 45000,  'desc' => 'Ayam Penyet Mbok Sri', 'cat' => $catMakan, 'wallet' => $cash->id],
            ['date' => '2026-07-24', 'type' => 'expense', 'amount' => 150000, 'desc' => 'Belanja Sayur & Buah', 'cat' => $catBelanja, 'wallet' => $cash->id],
            ['date' => '2026-07-25', 'type' => 'expense', 'amount' => 250000, 'desc' => 'Bayar Tagihan Indihome', 'cat' => $catTagihan, 'wallet' => $bca->id],
            ['date' => '2026-07-26', 'type' => 'expense', 'amount' => 75000,  'desc' => 'Nonton XXI Weekend', 'cat' => $catHiburan, 'wallet' => $gopay->id],

            // Week 2 (July 28 - August 3)
            ['date' => '2026-07-28', 'type' => 'expense', 'amount' => 30000,  'desc' => 'Nasi Goreng Gila', 'cat' => $catMakan, 'wallet' => $cash->id],
            ['date' => '2026-07-29', 'type' => 'expense', 'amount' => 100000, 'desc' => 'Isi Bensin Motor', 'cat' => $catTransport, 'wallet' => $bca->id],
            ['date' => '2026-07-30', 'type' => 'expense', 'amount' => 200000, 'desc' => 'Token Listrik PLN', 'cat' => $catTagihan, 'wallet' => $bca->id],
            ['date' => '2026-07-31', 'type' => 'expense', 'amount' => 350000, 'desc' => 'Belanja Mingguan Indomaret', 'cat' => $catBelanja, 'wallet' => $bca->id],
            ['date' => '2026-08-01', 'type' => 'expense', 'amount' => 60000,  'desc' => 'Makan Bakso Solo', 'cat' => $catMakan, 'wallet' => $cash->id],
            ['date' => '2026-08-02', 'type' => 'expense', 'amount' => 50000,  'desc' => 'Obat & Vitamin Apotek', 'cat' => $catKesehatan, 'wallet' => $cash->id],

            // Week 3 (August 4 - August 10)
            ['date' => '2026-08-04', 'type' => 'expense', 'amount' => 40000,  'desc' => 'Gojek ke Kantor', 'cat' => $catTransport, 'wallet' => $gopay->id],
            ['date' => '2026-08-05', 'type' => 'expense', 'amount' => 85000,  'desc' => 'Makan Siang Resto Sederhana', 'cat' => $catMakan, 'wallet' => $bca->id],
            ['date' => '2026-08-06', 'type' => 'expense', 'amount' => 186000, 'desc' => 'Langganan Netflix & Spotify', 'cat' => $catTagihan, 'wallet' => $bca->id],
            ['date' => '2026-08-07', 'type' => 'expense', 'amount' => 120000, 'desc' => 'Isi Bensin Shell', 'cat' => $catTransport, 'wallet' => $bca->id],
            ['date' => '2026-08-08', 'type' => 'expense', 'amount' => 220000, 'desc' => 'Dinner Kafe Bareng Teman', 'cat' => $catMakan, 'wallet' => $bca->id],
            ['date' => '2026-08-09', 'type' => 'expense', 'amount' => 150000, 'desc' => 'Beli Baju Kaos', 'cat' => $catBelanja, 'wallet' => $gopay->id],

            // Week 4 (August 11 - August 19)
            ['date' => '2026-08-11', 'type' => 'expense', 'amount' => 30000,  'desc' => 'Makan Mie Ayam', 'cat' => $catMakan, 'wallet' => $cash->id],
            ['date' => '2026-08-12', 'type' => 'expense', 'amount' => 50000,  'desc' => 'Pulsa Data Telkomsel', 'cat' => $catTagihan, 'wallet' => $gopay->id],
            ['date' => '2026-08-13', 'type' => 'expense', 'amount' => 100000, 'desc' => 'Isi Bensin motor', 'cat' => $catTransport, 'wallet' => $bca->id],
            ['date' => '2026-08-14', 'type' => 'expense', 'amount' => 45000,  'desc' => 'Kopi Starbucks Late', 'cat' => $catMakan, 'wallet' => $gopay->id],
            ['date' => '2026-08-15', 'type' => 'expense', 'amount' => 280000, 'desc' => 'Belanja Supermarket Hero', 'cat' => $catBelanja, 'wallet' => $bca->id],
            ['date' => '2026-08-16', 'type' => 'expense', 'amount' => 90000,  'desc' => 'Topup Game Steam', 'cat' => $catHiburan, 'wallet' => $gopay->id],
            ['date' => '2026-08-17', 'type' => 'expense', 'amount' => 55000,  'desc' => 'Makan Sate Ayam 17an', 'cat' => $catMakan, 'wallet' => $cash->id],
            ['date' => '2026-08-18', 'type' => 'expense', 'amount' => 35000,  'desc' => 'Beli Rokok Sampoerna', 'cat' => $catLainnyaEx, 'wallet' => $cash->id],
            ['date' => '2026-08-19', 'type' => 'expense', 'amount' => 30000,  'desc' => 'Makan nasgor', 'cat' => $catMakan, 'wallet' => $cash->id],
            ['date' => '2026-08-19', 'type' => 'expense', 'amount' => 15000,  'desc' => 'Ngopi sore', 'cat' => $catMakan, 'wallet' => $cash->id],
            ['date' => '2026-08-19', 'type' => 'expense', 'amount' => 10000,  'desc' => 'Es krim cone', 'cat' => $catMakan, 'wallet' => $cash->id],
            ['date' => '2026-08-19', 'type' => 'expense', 'amount' => 100000, 'desc' => 'Isi bensin Pertamax', 'cat' => $catTransport, 'wallet' => $cash->id],
        ];

        foreach ($transactionsData as $tx) {
            if (!$tx['cat']) continue;

            Transaction::create([
                'user_id'          => $userId,
                'wallet_id'        => $tx['wallet'],
                'category_id'      => $tx['cat'],
                'type'             => $tx['type'],
                'amount'           => $tx['amount'],
                'description'      => $tx['desc'],
                'transaction_date' => $tx['date'],
                'is_reviewed'      => true,
                'ai_confidence'    => 0.95,
            ]);
        }

        // ── 5. Recalculate Wallet Balances ──
        $wallets = [$cash, $bca, $gopay];
        foreach ($wallets as $w) {
            $in  = Transaction::where('user_id', $userId)->where('wallet_id', $w->id)->where('type', 'income')->sum('amount');
            $out = Transaction::where('user_id', $userId)->where('wallet_id', $w->id)->where('type', 'expense')->sum('amount');
            $w->update(['balance' => max(0, $in - $out)]);
        }
    }
}
