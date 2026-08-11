<?php

namespace Tests\Unit\AI;

use App\Services\AI\Providers\MockAIProvider;
use App\Enums\MessageIntent;
use Tests\TestCase;

/**
 * Stage 1 — Intent Classifier Test Suite
 *
 * Target: ≥90% pass rate (27/30 cases must pass).
 * Tests realistic Indonesian user messages including slang, typos, and edge cases.
 */
class IntentClassifierTest extends TestCase
{
    private MockAIProvider $ai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ai = new MockAIProvider();
    }

    // ─────────────────────────────────────────────────────
    //  AddTransaction — catat transaksi baru
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_detects_add_transaction_standard(): void
    {
        $result = $this->ai->classifyIntent('beli nasi goreng 15000');
        $this->assertEquals(MessageIntent::AddTransaction, $result->intent, 'Kalimat baku harus dikenali');
    }

    /** @test */
    public function it_detects_add_transaction_with_rb_abbreviation(): void
    {
        $result = $this->ai->classifyIntent('makan siang 15rb');
        $this->assertEquals(MessageIntent::AddTransaction, $result->intent, '"rb" harus dikenali sebagai transaksi');
    }

    /** @test */
    public function it_detects_add_transaction_with_k_abbreviation(): void
    {
        $result = $this->ai->classifyIntent('kopi 20k');
        $this->assertEquals(MessageIntent::AddTransaction, $result->intent, '"k" harus dikenali sebagai transaksi');
    }

    /** @test */
    public function it_detects_add_transaction_with_slang(): void
    {
        $result = $this->ai->classifyIntent('jajan 25k bosq');
        $this->assertEquals(MessageIntent::AddTransaction, $result->intent, 'Bahasa gaul "bosq" tidak boleh mengacaukan intent');
    }

    /** @test */
    public function it_detects_add_transaction_without_punctuation(): void
    {
        $result = $this->ai->classifyIntent('beli indomie 3500 malam tadi');
        $this->assertEquals(MessageIntent::AddTransaction, $result->intent, 'Tanpa tanda baca harus tetap dikenali');
    }

    /** @test */
    public function it_detects_add_transaction_with_juta(): void
    {
        $result = $this->ai->classifyIntent('bayar sewa kos 1.5jt');
        $this->assertEquals(MessageIntent::AddTransaction, $result->intent, '"jt" harus dikenali sebagai transaksi');
    }

    /** @test */
    public function it_detects_add_transaction_income(): void
    {
        $result = $this->ai->classifyIntent('gajian bulan ini masuk 3 juta');
        $this->assertEquals(MessageIntent::AddTransaction, $result->intent, 'Pemasukan gaji harus dikenali sebagai AddTransaction');
    }

    /** @test */
    public function it_detects_add_transaction_informal_verb(): void
    {
        $result = $this->ai->classifyIntent('ngeluarin 50rb buat bensin');
        $this->assertEquals(MessageIntent::AddTransaction, $result->intent, '"ngeluarin" harus dikenali sebagai pengeluaran');
    }

    // ─────────────────────────────────────────────────────
    //  DeleteTransaction — hapus transaksi
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_detects_delete_transaction_standard(): void
    {
        $result = $this->ai->classifyIntent('hapus transaksi kopi tadi');
        $this->assertEquals(MessageIntent::DeleteTransaction, $result->intent, '"hapus" harus dikenali sebagai delete');
    }

    /** @test */
    public function it_detects_delete_transaction_casual(): void
    {
        $result = $this->ai->classifyIntent('hapusin yang tadi');
        $this->assertEquals(MessageIntent::DeleteTransaction, $result->intent, '"hapusin" (informal) harus dikenali');
    }

    /** @test */
    public function it_detects_delete_transaction_alternative_verb(): void
    {
        $result = $this->ai->classifyIntent('hilangkan transaksi nasi goreng');
        $this->assertEquals(MessageIntent::DeleteTransaction, $result->intent, '"hilangkan" harus dikenali sebagai delete');
    }

    /** @test */
    public function it_detects_delete_last_transaction(): void
    {
        $result = $this->ai->classifyIntent('hapus transaksi terakhir');
        $this->assertEquals(MessageIntent::DeleteTransaction, $result->intent, '"hapus transaksi terakhir" harus terdeteksi');
    }

    // ─────────────────────────────────────────────────────
    //  Correction — koreksi transaksi yang sudah ada
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_detects_correction_harusnya(): void
    {
        $result = $this->ai->classifyIntent('yang tadi harusnya 30rb bukan 20rb');
        $this->assertEquals(MessageIntent::Correction, $result->intent, '"harusnya" harus dikenali sebagai koreksi');
    }

    /** @test */
    public function it_detects_correction_salah(): void
    {
        $result = $this->ai->classifyIntent('eh yang tadi salah, kopi tadi 25rb bukan 20rb');
        $this->assertEquals(MessageIntent::Correction, $result->intent, '"salah" harus dikenali sebagai koreksi');
    }

    /** @test */
    public function it_detects_correction_wallet_change(): void
    {
        $result = $this->ai->classifyIntent('kopi tadi bukan cash, pake gopay');
        $this->assertEquals(MessageIntent::Correction, $result->intent, 'Koreksi wallet harus terdeteksi');
    }

    /** @test */
    public function it_detects_correction_ralat(): void
    {
        $result = $this->ai->classifyIntent('ralat bensin tadi 60rb bukan 50rb');
        $this->assertEquals(MessageIntent::Correction, $result->intent, '"ralat" harus dikenali sebagai koreksi');
    }

    // ─────────────────────────────────────────────────────
    //  InspectRecords — lihat data
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_detects_inspect_balance(): void
    {
        $result = $this->ai->classifyIntent('berapa saldo saya sekarang?');
        // "berapa" bisa masuk QueryReport atau InspectRecords — keduanya valid
        $this->assertContains($result->intent, [MessageIntent::InspectRecords, MessageIntent::QueryReport], '"saldo" harus masuk inspect atau query');
    }

    /** @test */
    public function it_detects_inspect_transaction_list(): void
    {
        $result = $this->ai->classifyIntent('daftar transaksi terakhir');
        $this->assertEquals(MessageIntent::InspectRecords, $result->intent, '"daftar transaksi" harus terdeteksi sebagai inspect');
    }

    /** @test */
    public function it_detects_inspect_riwayat(): void
    {
        $result = $this->ai->classifyIntent('tampilkan riwayat transaksi bulan ini');
        $this->assertContains($result->intent, [MessageIntent::InspectRecords, MessageIntent::QueryReport], '"riwayat" harus masuk inspect atau query');
    }

    // ─────────────────────────────────────────────────────
    //  QueryReport — laporan/statistik
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_detects_query_total_spending(): void
    {
        $result = $this->ai->classifyIntent('bulan ini udah abis berapa?');
        $this->assertEquals(MessageIntent::QueryReport, $result->intent, '"abis berapa" harus dikenali sebagai query report');
    }

    /** @test */
    public function it_detects_query_informal(): void
    {
        $result = $this->ai->classifyIntent('sisa duit gue?');
        $this->assertContains($result->intent, [MessageIntent::QueryReport, MessageIntent::InspectRecords], 'Pertanyaan saldo informal harus masuk query/inspect');
    }

    /** @test */
    public function it_detects_query_comparison(): void
    {
        $result = $this->ai->classifyIntent('pengeluaran bulan ini dibanding bulan lalu gimana?');
        $this->assertEquals(MessageIntent::QueryReport, $result->intent, '"dibanding" harus dikenali sebagai query trend');
    }

    // ─────────────────────────────────────────────────────
    //  ManageRecords — kelola wallet/kategori/budget
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_detects_manage_create_wallet(): void
    {
        $result = $this->ai->classifyIntent('buat wallet baru gopay');
        $this->assertEquals(MessageIntent::ManageRecords, $result->intent, '"buat wallet" harus dikenali sebagai manage');
    }

    /** @test */
    public function it_detects_manage_rename_wallet(): void
    {
        $result = $this->ai->classifyIntent('rename wallet Cash jadi Tunai');
        $this->assertEquals(MessageIntent::ManageRecords, $result->intent, '"rename" harus dikenali sebagai manage');
    }

    // ─────────────────────────────────────────────────────
    //  GreetingSmallTalk — sapaan
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_detects_greeting_halo(): void
    {
        $result = $this->ai->classifyIntent('halo!');
        $this->assertEquals(MessageIntent::GreetingSmallTalk, $result->intent, '"halo" harus dikenali sebagai sapaan');
    }

    /** @test */
    public function it_detects_greeting_selamat_pagi(): void
    {
        $result = $this->ai->classifyIntent('selamat pagi');
        // "pagi" adalah keyword greeting
        $this->assertEquals(MessageIntent::GreetingSmallTalk, $result->intent, '"selamat pagi" harus dikenali sebagai sapaan');
    }

    // ─────────────────────────────────────────────────────
    //  Edge Cases — kasus batas
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_handles_greeting_plus_transaction(): void
    {
        // "halo, baru beli bensin 50rb" — ada angka, intent AddTransaction lebih tepat
        $result = $this->ai->classifyIntent('halo baru beli bensin 50rb');
        // Acceptable: AddTransaction (angka ada) atau GreetingSmallTalk
        $this->assertContains($result->intent, [MessageIntent::AddTransaction, MessageIntent::GreetingSmallTalk], 'Sapaan + transaksi — salah satu intent valid diterima');
    }

    /** @test */
    public function it_returns_unclear_for_gibberish(): void
    {
        $result = $this->ai->classifyIntent('asdfghjkl');
        $this->assertEquals(MessageIntent::Unclear, $result->intent, 'Pesan tidak bermakna harus menghasilkan Unclear');
    }

    /** @test */
    public function it_has_reasonable_confidence_score(): void
    {
        $result = $this->ai->classifyIntent('beli kopi 20rb');
        $this->assertGreaterThanOrEqual(0.5, $result->confidence, 'Confidence harus ≥ 0.5 untuk pesan jelas');
        $this->assertLessThanOrEqual(1.0, $result->confidence, 'Confidence tidak boleh > 1.0');
    }
}
