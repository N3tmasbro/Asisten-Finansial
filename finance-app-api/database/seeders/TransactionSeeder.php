<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Temukan user pertama (atau spesifik)
        $user = User::first();
        
        if (!$user) {
            $this->command->warn('Tidak ada user ditemukan. Seeder dibatalkan.');
            return;
        }

        // Ambil atau buat Dompet (Wallets)
        $cashWallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'type' => 'cash'],
            ['name' => 'Dompet Tunai', 'balance' => 0]
        );
        $bankWallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'type' => 'bank'],
            ['name' => 'BCA', 'balance' => 0]
        );
        $ewallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'type' => 'ewallet'],
            ['name' => 'GoPay', 'balance' => 0]
        );

        $wallets = [$cashWallet, $bankWallet, $ewallet];

        // Ambil kategori global atau milik user
        $catMakan = Category::where('name', 'Makan & Minum')->first() ?? Category::first();
        $catTransport = Category::where('name', 'Transport')->first() ?? Category::first();
        $catBelanja = Category::where('name', 'Belanja')->first() ?? Category::first();
        $catTagihan = Category::where('name', 'Tagihan')->first() ?? Category::first();
        $catGaji = Category::where('name', 'Gaji')->first() ?? Category::first();

        if (!$catMakan) {
            $this->command->warn('Tidak ada kategori ditemukan. Jalankan CategorySeeder terlebih dahulu.');
            return;
        }

        // Hapus simulasi transaksi lama jika ada (opsional, tapi disarankan agar tidak dobel jika dijalankan berulang)
        // Kita hanya tambahkan saja sesuai instruksi user (append), tapi biar realistis kita buat data 30 hari ke belakang.

        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $transactionsToInsert = [];

        // 1. Pemasukan Gaji di awal bulan/awal periode
        $transactionsToInsert[] = [
            'user_id' => $user->id,
            'wallet_id' => $bankWallet->id,
            'category_id' => $catGaji->id,
            'type' => 'income',
            'amount' => 8500000,
            'description' => 'Gaji Bulanan',
            'transaction_date' => $startDate->copy()->addDays(1)->format('Y-m-d'),
            'is_reviewed' => true,
            'raw_input' => 'Gaji bulanan 8,5jt',
            'ai_confidence' => 0.95,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // 2. Transaksi harian
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            
            // Makan harian (1-2 kali sehari)
            for ($i = 0; $i < rand(1, 2); $i++) {
                $transactionsToInsert[] = [
                    'user_id' => $user->id,
                    'wallet_id' => $cashWallet->id,
                    'category_id' => $catMakan->id,
                    'type' => 'expense',
                    'amount' => rand(15, 50) * 1000,
                    'description' => ['Makan siang', 'Kopi', 'Cemilan', 'Makan malam'][array_rand(['Makan siang', 'Kopi', 'Cemilan', 'Makan malam'])],
                    'transaction_date' => $currentDate->copy()->format('Y-m-d'),
                    'is_reviewed' => true,
                    'raw_input' => 'Beli makan',
                    'ai_confidence' => 0.88,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Transport (Gojek / Bensin, probabilitas 60%)
            if (rand(1, 100) <= 60) {
                $transactionsToInsert[] = [
                    'user_id' => $user->id,
                    'wallet_id' => $ewallet->id,
                    'category_id' => $catTransport->id,
                    'type' => 'expense',
                    'amount' => rand(10, 30) * 1000,
                    'description' => ['Gojek', 'Bensin motor', 'Grab', 'Parkir'][array_rand(['Gojek', 'Bensin motor', 'Grab', 'Parkir'])],
                    'transaction_date' => $currentDate->copy()->format('Y-m-d'),
                    'is_reviewed' => rand(0, 1) ? true : false, // Beberapa butuh review
                    'raw_input' => 'bayar gojek',
                    'ai_confidence' => rand(70, 99) / 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Belanja (Probabilitas 20%)
            if (rand(1, 100) <= 20) {
                $transactionsToInsert[] = [
                    'user_id' => $user->id,
                    'wallet_id' => $bankWallet->id,
                    'category_id' => $catBelanja->id,
                    'type' => 'expense',
                    'amount' => rand(50, 300) * 1000,
                    'description' => 'Belanja minimarket',
                    'transaction_date' => $currentDate->copy()->format('Y-m-d'),
                    'is_reviewed' => true,
                    'raw_input' => 'belanja di alfa',
                    'ai_confidence' => 0.90,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Tagihan (Probabilitas 5%)
            if (rand(1, 100) <= 5) {
                $transactionsToInsert[] = [
                    'user_id' => $user->id,
                    'wallet_id' => $bankWallet->id,
                    'category_id' => $catTagihan->id,
                    'type' => 'expense',
                    'amount' => rand(150, 500) * 1000,
                    'description' => ['Listrik', 'Internet', 'BPJS'][array_rand(['Listrik', 'Internet', 'BPJS'])],
                    'transaction_date' => $currentDate->copy()->format('Y-m-d'),
                    'is_reviewed' => true,
                    'raw_input' => 'bayar tagihan bulanan',
                    'ai_confidence' => 0.92,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $currentDate->addDay();
        }

        // Insert massal
        foreach (array_chunk($transactionsToInsert, 100) as $chunk) {
            Transaction::insert($chunk);
        }

        // Rekalkulasi saldo semua dompet (karena insert massal tidak memicu event Model)
        $this->command->info('Merekonstruksi saldo dompet...');
        
        foreach ($wallets as $wallet) {
            $totalIncome = Transaction::where('wallet_id', $wallet->id)->where('type', 'income')->sum('amount');
            $totalExpense = Transaction::where('wallet_id', $wallet->id)->where('type', 'expense')->sum('amount');
            
            $wallet->balance = $totalIncome - $totalExpense;
            $wallet->save();
        }

        $this->command->info('Berhasil men-generate ' . count($transactionsToInsert) . ' transaksi simulasi!');
    }
}
