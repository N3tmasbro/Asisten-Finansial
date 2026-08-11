<?php

namespace Tests\Unit\AI;

use App\Services\AI\Providers\MockAIProvider;
use Tests\TestCase;

/**
 * Stage 2C — Query Parser Test Suite
 *
 * Target: ≥85% pass rate (13/15 cases must pass).
 * Tests query type detection (category, period, trend, balance) and period parsing.
 */
class QueryParserTest extends TestCase
{
    private MockAIProvider $ai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ai = new MockAIProvider();
    }

    // ─────────────────────────────────────────────────────
    //  Query Type Detection
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_detects_total_by_period(): void
    {
        $result = $this->ai->parseQuery('bulan ini habis berapa?');
        $this->assertEquals('total_by_period', $result->queryType, '"habis berapa" harus return total_by_period');
    }

    /** @test */
    public function it_detects_total_by_category(): void
    {
        $result = $this->ai->parseQuery('pengeluaran berdasarkan kategori bulan ini');
        $this->assertEquals('total_by_category', $result->queryType, '"kategori" harus return total_by_category');
    }

    /** @test */
    public function it_detects_trend_comparison(): void
    {
        $result = $this->ai->parseQuery('pengeluaran bulan ini dibanding bulan lalu');
        $this->assertEquals('trend_comparison', $result->queryType, '"dibanding" harus return trend_comparison');
    }

    /** @test */
    public function it_detects_balance_prediction(): void
    {
        $result = $this->ai->parseQuery('prediksi saldo saya sampai akhir bulan');
        $this->assertEquals('balance_prediction', $result->queryType, '"prediksi" harus return balance_prediction');
    }

    /** @test */
    public function it_detects_top_spending(): void
    {
        $result = $this->ai->parseQuery('pengeluaran terbesar bulan ini apa?');
        $this->assertEquals('top_spending', $result->queryType, '"terbesar" harus return top_spending');
    }

    /** @test */
    public function it_defaults_to_general_summary(): void
    {
        $result = $this->ai->parseQuery('gimana keuangan aku?');
        $this->assertEquals('general_summary', $result->queryType, 'Pertanyaan umum harus return general_summary');
    }

    // ─────────────────────────────────────────────────────
    //  Period Detection
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_detects_period_this_month_default(): void
    {
        $result = $this->ai->parseQuery('total pengeluaran bulan ini');
        $this->assertEquals('this_month', $result->period, '"bulan ini" harus return this_month');
    }

    /** @test */
    public function it_detects_period_last_month(): void
    {
        $result = $this->ai->parseQuery('berapa habis bulan lalu?');
        $this->assertEquals('last_month', $result->period, '"bulan lalu" harus return last_month');
    }

    /** @test */
    public function it_detects_period_today(): void
    {
        $result = $this->ai->parseQuery('pengeluaran hari ini berapa?');
        $this->assertEquals('today', $result->period, '"hari ini" harus return today');
    }

    /** @test */
    public function it_detects_period_this_week(): void
    {
        $result = $this->ai->parseQuery('minggu ini keluar berapa?');
        $this->assertEquals('this_week', $result->period, '"minggu ini" harus return this_week');
    }

    /** @test */
    public function it_detects_period_last_week(): void
    {
        $result = $this->ai->parseQuery('minggu lalu total pengeluaran?');
        $this->assertEquals('last_week', $result->period, '"minggu lalu" harus return last_week');
    }

    // ─────────────────────────────────────────────────────
    //  Category Filter
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_detects_category_filter_makan(): void
    {
        $result = $this->ai->parseQuery('bulan ini habis berapa buat makan?');
        $this->assertEquals('Makan & Minum', $result->categoryFilter, '"makan" harus mendeteksi filter Makan & Minum');
    }

    /** @test */
    public function it_detects_category_filter_transport(): void
    {
        $result = $this->ai->parseQuery('pengeluaran transport minggu ini berapa?');
        $this->assertEquals('Transport', $result->categoryFilter, '"transport" harus mendeteksi filter Transport');
    }

    // ─────────────────────────────────────────────────────
    //  Edge Cases
    // ─────────────────────────────────────────────────────

    /** @test */
    public function it_returns_null_category_filter_for_general_query(): void
    {
        $result = $this->ai->parseQuery('total pengeluaran bulan ini?');
        $this->assertNull($result->categoryFilter, 'Query umum tidak boleh ada category filter');
    }

    /** @test */
    public function it_detects_informal_balance_question(): void
    {
        $result = $this->ai->parseQuery('saldo aku sisa berapa ya?');
        // "sisa" termasuk query keywords
        $this->assertNotNull($result->queryType, 'Pertanyaan saldo informal harus menghasilkan query type');
        $this->assertIsString($result->queryType);
    }
}
