<?php

namespace Tests\Unit\AI;

use App\Services\AI\Providers\MockAIProvider;
use Tests\TestCase;

/**
 * Stage 2B — Financial Command Parser Test Suite
 *
 * Target: ≥80% pass rate (16/20 cases must pass).
 * Tests delete, correction, wallet/category/budget management commands.
 */
class FinancialCommandTest extends TestCase
{
    private MockAIProvider $ai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ai = new MockAIProvider();
    }

    // ─────────────────────────────────────────────────────
    //  Delete Transaction
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_parses_delete_command_standard(): void
    {
        $result = $this->ai->parseFinancialCommand('hapus transaksi kopi tadi');
        $this->assertEquals('delete_transaction', $result->action, '"hapus transaksi" harus return delete_transaction');
    }

    /** @test */
    public function it_parses_delete_command_with_target(): void
    {
        $result = $this->ai->parseFinancialCommand('hapus yang nasi goreng 15rb');
        $this->assertEquals('delete_transaction', $result->action);
        $this->assertNotEmpty($result->target['description'] ?? '', 'Target deskripsi harus ada');
    }

    /** @test */
    public function it_parses_delete_command_informal(): void
    {
        $result = $this->ai->parseFinancialCommand('hapusin kopi tadi');
        $this->assertEquals('delete_transaction', $result->action, '"hapusin" informal harus dikenali');
    }

    // ─────────────────────────────────────────────────────
    //  Correction — update_transaction
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_parses_correction_amount(): void
    {
        $result = $this->ai->parseFinancialCommand('yang kopi tadi harusnya 30rb bukan 20rb');
        $this->assertEquals('update_transaction', $result->action, '"harusnya" harus return update_transaction');
        $this->assertEquals(30000, $result->changes['amount'] ?? null, 'Amount baru 30rb harus terparse');
    }

    /** @test */
    public function it_parses_correction_with_salah(): void
    {
        $result = $this->ai->parseFinancialCommand('transaksi bensin tadi salah, seharusnya 60rb');
        $this->assertEquals('update_transaction', $result->action, '"salah...seharusnya" harus dikenali sebagai koreksi');
    }

    /** @test */
    public function it_parses_correction_wallet_change(): void
    {
        $result = $this->ai->parseFinancialCommand('yang kopi tadi bukan cash tapi pake gopay');
        $this->assertEquals('update_transaction', $result->action, 'Koreksi wallet harus dikenali');
        $this->assertNotEmpty($result->changes['wallet'] ?? '', 'Wallet baru harus ada di changes');
    }

    /** @test */
    public function it_parses_correction_category_change(): void
    {
        $result = $this->ai->parseFinancialCommand('yang nasi goreng tadi harusnya kategori Lainnya');
        $this->assertEquals('update_transaction', $result->action, 'Koreksi kategori harus dikenali');
    }

    // ─────────────────────────────────────────────────────
    //  Wallet Management
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_parses_create_wallet(): void
    {
        $result = $this->ai->parseFinancialCommand('buat wallet gopay');
        $this->assertEquals('create_wallet', $result->action, '"buat wallet" harus return create_wallet');
        $this->assertNotEmpty($result->data['name'] ?? '', 'Nama wallet harus ada');
    }

    /** @test */
    public function it_parses_create_wallet_tambah_keyword(): void
    {
        $result = $this->ai->parseFinancialCommand('tambah wallet OVO');
        $this->assertEquals('create_wallet', $result->action, '"tambah wallet" harus dikenali');
    }

    /** @test */
    public function it_parses_rename_wallet(): void
    {
        $result = $this->ai->parseFinancialCommand('rename wallet Cash jadi Tunai');
        $this->assertEquals('rename_wallet', $result->action, '"rename wallet" harus return rename_wallet');
        $this->assertEquals('Tunai', $result->data['new_name'] ?? null, 'Nama baru harus "Tunai"');
    }

    // ─────────────────────────────────────────────────────
    //  Category Management
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_parses_create_category(): void
    {
        $result = $this->ai->parseFinancialCommand('buat kategori Investasi');
        $this->assertEquals('create_category', $result->action, '"buat kategori" harus return create_category');
        $this->assertEquals('Investasi', $result->data['name'] ?? null, 'Nama kategori harus "Investasi"');
    }

    /** @test */
    public function it_parses_create_category_tambah_keyword(): void
    {
        $result = $this->ai->parseFinancialCommand('tambah kategori Olahraga');
        $this->assertEquals('create_category', $result->action, '"tambah kategori" harus dikenali');
    }

    // ─────────────────────────────────────────────────────
    //  Budget Management
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_parses_create_budget(): void
    {
        $result = $this->ai->parseFinancialCommand('buat budget Makan & Minum 500rb');
        $this->assertEquals('create_budget', $result->action, '"buat budget" harus return create_budget');
        $this->assertEquals(500000, $result->data['amount'] ?? null, 'Amount budget harus 500000');
    }

    /** @test */
    public function it_parses_update_budget_naikkan(): void
    {
        $result = $this->ai->parseFinancialCommand('budget Transport naikkan jadi 300rb');
        $this->assertEquals('update_budget', $result->action, '"naikkan budget" harus return update_budget');
    }

    // ─────────────────────────────────────────────────────
    //  Edge Cases
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_returns_unknown_for_unrecognized_command(): void
    {
        $result = $this->ai->parseFinancialCommand('tolong bantuin aku dong');
        $this->assertEquals('unknown', $result->action, 'Perintah tidak dikenali harus return "unknown"');
    }

    /** @test */
    public function it_handles_delete_with_last_transaction_keyword(): void
    {
        $result = $this->ai->parseFinancialCommand('hapus transaksi terakhir');
        $this->assertEquals('delete_transaction', $result->action, '"hapus transaksi terakhir" harus dikenali');
    }

    /** @test */
    public function it_has_reasonable_confidence(): void
    {
        $result = $this->ai->parseFinancialCommand('hapus transaksi kopi 20rb');
        $this->assertGreaterThanOrEqual(0.3, $result->confidence ?? 0, 'Confidence minimum 0.3');
        $this->assertLessThanOrEqual(1.0, $result->confidence ?? 0, 'Confidence tidak boleh > 1.0');
    }

    /** @test */
    public function it_parses_delete_hilangkan_keyword(): void
    {
        $result = $this->ai->parseFinancialCommand('hilangkan transaksi nasi padang kemarin');
        $this->assertEquals('delete_transaction', $result->action, '"hilangkan" harus dikenali sebagai delete');
    }

    /** @test */
    public function it_parses_correction_amount_with_seharusnya(): void
    {
        $result = $this->ai->parseFinancialCommand('transaksi nasi tadi seharusnya 25rb');
        $this->assertEquals('update_transaction', $result->action, '"seharusnya" harus dikenali sebagai koreksi');
    }
}
