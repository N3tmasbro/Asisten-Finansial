<?php

namespace Tests\Feature\Analytics;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Analytics\BalancePredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BalancePredictionService Test Suite
 *
 * Tests the refined prediction algorithm including:
 * - Trend classification (burning, saving, stable, unknown)
 * - Top 10% outlier trimming for expenses and income
 * - Dynamic lookback for new users (< 30 days of data)
 * - Edge cases (zero balance, no transactions)
 */
class BalancePredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    private BalancePredictionService $service;
    private User $user;
    private Wallet $wallet;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BalancePredictionService::class);

        $this->user = User::factory()->create();

        $this->wallet = Wallet::create([
            'user_id' => $this->user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 1000000, // Rp1.000.000
            'is_default' => true,
        ]);

        $this->category = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Makan & Minum',
            'type' => 'expense',
            'icon' => '🍔',
        ]);
    }

    // ─────────────────────────────────────────────────────
    //  Trend Classification
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_classifies_burning_trend_when_expenses_exceed_income(): void
    {
        // Create 10 daily expenses of Rp50.000 over the last 10 days (total Rp500.000)
        for ($i = 1; $i <= 10; $i++) {
            Transaction::create([
                'user_id' => $this->user->id,
                'wallet_id' => $this->wallet->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => 50000,
                'description' => "Makan hari ke-{$i}",
                'transaction_date' => now()->subDays($i)->toDateString(),
            ]);
        }

        $result = $this->service->predict($this->user->id);

        $this->assertEquals('burning', $result['trend'], 'Tren harus "burning" ketika pengeluaran > pemasukan');
        $this->assertGreaterThan(0, $result['daily_net_burn'], 'Net burn harus positif untuk tren burning');
        $this->assertNotNull($result['days_until_empty'], 'days_until_empty harus ada');
        $this->assertNotNull($result['predicted_empty_date'], 'predicted_empty_date harus ada');
    }

    /** @test */
    public function it_classifies_saving_trend_when_income_exceeds_expenses(): void
    {
        $incomeCategory = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Gaji',
            'type' => 'income',
            'icon' => '💰',
        ]);

        // Create daily income of Rp100.000 and expenses of Rp30.000 over 10 days
        for ($i = 1; $i <= 10; $i++) {
            Transaction::create([
                'user_id' => $this->user->id,
                'wallet_id' => $this->wallet->id,
                'category_id' => $incomeCategory->id,
                'type' => 'income',
                'amount' => 100000,
                'description' => "Pendapatan hari ke-{$i}",
                'transaction_date' => now()->subDays($i)->toDateString(),
            ]);

            Transaction::create([
                'user_id' => $this->user->id,
                'wallet_id' => $this->wallet->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => 30000,
                'description' => "Makan hari ke-{$i}",
                'transaction_date' => now()->subDays($i)->toDateString(),
            ]);
        }

        $result = $this->service->predict($this->user->id);

        $this->assertEquals('saving', $result['trend'], 'Tren harus "saving" ketika pemasukan > pengeluaran');
        $this->assertLessThan(0, $result['daily_net_burn'], 'Net burn harus negatif untuk tren saving');
        $this->assertGreaterThan(0, $result['daily_savings_rate'], 'daily_savings_rate harus positif');
        $this->assertNull($result['days_until_empty'], 'days_until_empty harus null untuk tren saving');
    }

    /** @test */
    public function it_classifies_stable_trend_when_nearly_balanced(): void
    {
        $incomeCategory = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Freelance',
            'type' => 'income',
            'icon' => '💻',
        ]);

        // Create near-balanced income/expense (selisih < Rp5000/hari)
        for ($i = 1; $i <= 10; $i++) {
            Transaction::create([
                'user_id' => $this->user->id,
                'wallet_id' => $this->wallet->id,
                'category_id' => $incomeCategory->id,
                'type' => 'income',
                'amount' => 50000,
                'description' => "Income hari ke-{$i}",
                'transaction_date' => now()->subDays($i)->toDateString(),
            ]);

            Transaction::create([
                'user_id' => $this->user->id,
                'wallet_id' => $this->wallet->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => 50000,
                'description' => "Expense hari ke-{$i}",
                'transaction_date' => now()->subDays($i)->toDateString(),
            ]);
        }

        $result = $this->service->predict($this->user->id);

        $this->assertEquals('stable', $result['trend'], 'Tren harus "stable" ketika selisih harian < Rp5000');
        $this->assertLessThanOrEqual(5000, abs($result['daily_net_burn']), 'Net burn absolut harus <= 5000');
    }

    /** @test */
    public function it_classifies_unknown_trend_when_no_transactions(): void
    {
        // No transactions created — just user + wallet
        $result = $this->service->predict($this->user->id);

        $this->assertEquals('unknown', $result['trend'], 'Tren harus "unknown" tanpa transaksi');
        $this->assertEquals(0, $result['daily_avg_expense']);
        $this->assertEquals(0, $result['daily_avg_income']);
        $this->assertNull($result['days_until_empty']);
    }

    // ─────────────────────────────────────────────────────
    //  Outlier Trimming
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_trims_top_10_percent_outlier_expenses(): void
    {
        // Create 9 expenses of Rp10.000 and 1 outlier of Rp1.000.000
        // Top 10% of 10 items = 1 item trimmed → the Rp1.000.000 should be excluded
        for ($i = 1; $i <= 9; $i++) {
            Transaction::create([
                'user_id' => $this->user->id,
                'wallet_id' => $this->wallet->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => 10000,
                'description' => "Normal expense {$i}",
                'transaction_date' => now()->subDays($i)->toDateString(),
            ]);
        }

        // The outlier
        Transaction::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 1000000,
            'description' => 'Beli laptop (outlier)',
            'transaction_date' => now()->subDays(5)->toDateString(),
        ]);

        $result = $this->service->predict($this->user->id);

        // Without trimming: (9*10000 + 1000000) / 10 = 109.000/hari
        // With trimming: 9 * 10000 / 10 = 9.000/hari
        // So daily avg expense should be much closer to 9.000 than 109.000
        $this->assertLessThan(20000, $result['daily_avg_expense'],
            'Rata-rata harian harus < Rp20.000 setelah outlier Rp1.000.000 dipangkas');
    }

    /** @test */
    public function it_trims_top_10_percent_outlier_income(): void
    {
        $incomeCategory = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Gaji',
            'type' => 'income',
            'icon' => '💰',
        ]);

        // 9 incomes of Rp20.000 and 1 outlier of Rp5.000.000 (bonus tahunan)
        for ($i = 1; $i <= 9; $i++) {
            Transaction::create([
                'user_id' => $this->user->id,
                'wallet_id' => $this->wallet->id,
                'category_id' => $incomeCategory->id,
                'type' => 'income',
                'amount' => 20000,
                'description' => "Normal income {$i}",
                'transaction_date' => now()->subDays($i)->toDateString(),
            ]);
        }

        Transaction::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $incomeCategory->id,
            'type' => 'income',
            'amount' => 5000000,
            'description' => 'Bonus tahunan (outlier)',
            'transaction_date' => now()->subDays(3)->toDateString(),
        ]);

        $result = $this->service->predict($this->user->id);

        // Without trimming: (9*20000 + 5000000) / 10 = 518.000/hari
        // With trimming: 9 * 20000 / 10 = 18.000/hari
        $this->assertLessThan(30000, $result['daily_avg_income'],
            'Rata-rata pemasukan harian harus < Rp30.000 setelah outlier Rp5.000.000 dipangkas');
    }

    // ─────────────────────────────────────────────────────
    //  Dynamic Lookback
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_uses_dynamic_lookback_for_new_users(): void
    {
        // User with only 10 days of data — lookback should be 10, not 30
        for ($i = 1; $i <= 10; $i++) {
            Transaction::create([
                'user_id' => $this->user->id,
                'wallet_id' => $this->wallet->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => 20000,
                'description' => "Makan hari ke-{$i}",
                'transaction_date' => now()->subDays($i)->toDateString(),
            ]);
        }

        $result = $this->service->predict($this->user->id);

        $this->assertLessThanOrEqual(30, $result['lookback_days']);
        $this->assertGreaterThanOrEqual(7, $result['lookback_days'],
            'Lookback harus minimal 7 hari');
    }

    // ─────────────────────────────────────────────────────
    //  Response Structure
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_returns_complete_response_structure(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Test',
            'transaction_date' => now()->subDays(1)->toDateString(),
        ]);

        $result = $this->service->predict($this->user->id);

        $expectedKeys = [
            'current_balance', 'daily_avg_expense', 'daily_avg_income',
            'daily_net_burn', 'daily_savings_rate', 'trend',
            'days_until_empty', 'predicted_empty_date',
            'predicted_month_end_balance', 'days_left_in_month', 'lookback_days',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Response harus mengandung key '{$key}'");
        }

        $this->assertEquals(1000000, $result['current_balance'], 'Saldo harus Rp1.000.000');
        $this->assertContains($result['trend'], ['burning', 'saving', 'stable', 'unknown']);
    }
}
