<?php

namespace Tests\Unit\AI;

use App\Services\AI\Providers\MockAIProvider;
use App\Enums\TransactionType;
use Tests\TestCase;

/**
 * Stage 2A — Transaction Extractor Test Suite
 *
 * Target: ≥85% pass rate (22/25 cases must pass).
 * Tests amount parsing, type detection, category matching, and multi-transaction splitting.
 */
class TransactionExtractorTest extends TestCase
{
    private MockAIProvider $ai;

    // Simulate user's categories
    private array $categories = [
        'Makan & Minum', 'Transport', 'Belanja', 'Tagihan',
        'Hiburan', 'Kesehatan', 'Pendidikan', 'Lainnya',
        'Gaji', 'Bonus/THR', 'Freelance/Sampingan',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ai = new MockAIProvider();
    }

    // ─────────────────────────────────────────────────────
    //  Amount Parsing — ekstraksi nominal
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_parses_amount_in_ribu(): void
    {
        $txs = $this->ai->extractTransactions('beli kopi 20ribu', $this->categories);
        $this->assertCount(1, $txs);
        $this->assertEquals(20000, $txs[0]->amount, '"20ribu" harus menjadi 20000');
    }

    /** @test */
    public function it_parses_amount_with_rb_abbreviation(): void
    {
        $txs = $this->ai->extractTransactions('makan siang 15rb', $this->categories);
        $this->assertCount(1, $txs);
        $this->assertEquals(15000, $txs[0]->amount, '"15rb" harus menjadi 15000');
    }

    /** @test */
    public function it_parses_amount_with_k_abbreviation(): void
    {
        $txs = $this->ai->extractTransactions('snack 25k', $this->categories);
        $this->assertCount(1, $txs);
        $this->assertEquals(25000, $txs[0]->amount, '"25k" harus menjadi 25000');
    }

    /** @test */
    public function it_parses_amount_with_juta(): void
    {
        $txs = $this->ai->extractTransactions('bayar kos 1.5jt', $this->categories);
        $this->assertCount(1, $txs);
        $this->assertEquals(1500000, $txs[0]->amount, '"1.5jt" harus menjadi 1500000');
    }

    /** @test */
    public function it_parses_amount_with_dot_separator(): void
    {
        $txs = $this->ai->extractTransactions('transfer 500.000', $this->categories);
        $this->assertCount(1, $txs);
        $this->assertEquals(500000, $txs[0]->amount, '"500.000" (format Indonesia) harus menjadi 500000');
    }

    /** @test */
    public function it_parses_amount_in_full_number(): void
    {
        $txs = $this->ai->extractTransactions('bayar listrik 75000', $this->categories);
        $this->assertCount(1, $txs);
        $this->assertEquals(75000, $txs[0]->amount, 'Angka bulat ≥1000 harus terparse sebagai amount');
    }

    // ─────────────────────────────────────────────────────
    //  Type Detection — expense vs income
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_defaults_to_expense(): void
    {
        $txs = $this->ai->extractTransactions('beli baju 150rb', $this->categories);
        $this->assertEquals(TransactionType::Expense, $txs[0]->type, 'Default harus expense');
    }

    /** @test */
    public function it_detects_income_gajian(): void
    {
        $txs = $this->ai->extractTransactions('gajian masuk 3 juta', $this->categories);
        $this->assertEquals(TransactionType::Income, $txs[0]->type, '"gajian" harus dikenali sebagai income');
    }

    /** @test */
    public function it_detects_income_dapet(): void
    {
        $txs = $this->ai->extractTransactions('dapet bonus 500rb', $this->categories);
        $this->assertEquals(TransactionType::Income, $txs[0]->type, '"dapet" harus dikenali sebagai income');
    }

    // ─────────────────────────────────────────────────────
    //  Category Detection — pencocokan kategori
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_detects_category_food_makan(): void
    {
        $txs = $this->ai->extractTransactions('makan siang nasi padang 25rb', $this->categories);
        $this->assertEquals('Makan & Minum', $txs[0]->categoryName, '"makan" harus masuk kategori Makan & Minum');
    }

    /** @test */
    public function it_detects_category_transport_bensin(): void
    {
        $txs = $this->ai->extractTransactions('isi bensin 50rb', $this->categories);
        $this->assertEquals('Transport', $txs[0]->categoryName, '"bensin" harus masuk kategori Transport');
    }

    /** @test */
    public function it_detects_category_tagihan_listrik(): void
    {
        $txs = $this->ai->extractTransactions('bayar listrik 150rb', $this->categories);
        $this->assertEquals('Tagihan', $txs[0]->categoryName, '"listrik" harus masuk kategori Tagihan');
    }

    /** @test */
    public function it_detects_category_hiburan_netflix(): void
    {
        $txs = $this->ai->extractTransactions('bayar netflix 54rb', $this->categories);
        $this->assertEquals('Hiburan', $txs[0]->categoryName, '"netflix" harus masuk kategori Hiburan');
    }

    /** @test */
    public function it_falls_back_to_lainnya_for_unknown(): void
    {
        $txs = $this->ai->extractTransactions('beli xyz abc 30rb', $this->categories);
        $this->assertEquals('Lainnya', $txs[0]->categoryName, 'Kata tidak dikenali harus fallback ke Lainnya');
    }

    // ─────────────────────────────────────────────────────
    //  Multi-Transaction — banyak transaksi dalam 1 pesan
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_splits_multi_transaction_with_dan(): void
    {
        $txs = $this->ai->extractTransactions('beli kopi 15rb dan nasi goreng 20rb', $this->categories);
        $this->assertGreaterThanOrEqual(2, count($txs), '"dan" harus memisahkan 2 transaksi');
    }

    /** @test */
    public function it_splits_multi_transaction_with_sama(): void
    {
        $txs = $this->ai->extractTransactions('makan siang 25rb sama kopi 15rb', $this->categories);
        $this->assertGreaterThanOrEqual(2, count($txs), '"sama" harus memisahkan 2 transaksi');
    }

    /** @test */
    public function it_splits_multi_transaction_with_comma(): void
    {
        $txs = $this->ai->extractTransactions('nasi 15rb, kopi 12rb, gorengan 5rb', $this->categories);
        $this->assertGreaterThanOrEqual(2, count($txs), 'Koma harus memisahkan transaksi');
    }

    // ─────────────────────────────────────────────────────
    //  Edge Cases
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_extracts_description_from_message(): void
    {
        $txs = $this->ai->extractTransactions('beli nasi goreng ayam 20rb', $this->categories);
        $this->assertNotEmpty($txs[0]->description, 'Deskripsi tidak boleh kosong');
        $this->assertIsString($txs[0]->description);
    }

    /** @test */
    public function it_has_reasonable_confidence(): void
    {
        $txs = $this->ai->extractTransactions('beli kopi 20rb', $this->categories);
        $this->assertGreaterThanOrEqual(0.3, $txs[0]->confidence, 'Confidence minimum 0.3');
        $this->assertLessThanOrEqual(1.0, $txs[0]->confidence, 'Confidence tidak boleh > 1.0');
    }

    /** @test */
    public function it_returns_at_least_one_transaction(): void
    {
        // Bahkan kalimat ambigu harus return minimal 1 item (fallback)
        $txs = $this->ai->extractTransactions('bayar sesuatu tadi', $this->categories);
        $this->assertNotEmpty($txs, 'Harus selalu return minimal 1 item');
    }

    /** @test */
    public function it_handles_amount_with_space(): void
    {
        $txs = $this->ai->extractTransactions('makan 20 ribu', $this->categories);
        $this->assertEquals(20000, $txs[0]->amount, '"20 ribu" (ada spasi) harus tetap terparse');
    }

    /** @test */
    public function it_handles_decimal_juta(): void
    {
        $txs = $this->ai->extractTransactions('cicilan motor 800rb', $this->categories);
        $this->assertEquals(800000, $txs[0]->amount, '"800rb" harus menjadi 800000');
    }
}
