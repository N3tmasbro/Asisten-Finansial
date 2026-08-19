<?php

namespace Tests\Feature\AI;

use App\Services\AI\Providers\GeminiProvider;
use App\Enums\MessageIntent;
use App\Enums\TransactionType;
use Tests\TestCase;

/**
 * Pengujian Khusus — Real Gemini API Provider Integration Test
 *
 * Runs test cases against the live Google Gemini API using the configured key.
 * Skipped if GEMINI_API_KEY is missing.
 */
class GeminiIntegrationTest extends TestCase
{
    private ?GeminiProvider $gemini = null;

    protected function setUp(): void
    {
        parent::setUp();

        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey) || str_contains($apiKey, 'your-api-key')) {
            $this->markTestSkipped('Pengujian dibatalkan: GEMINI_API_KEY tidak dikonfigurasi.');
        }

        $this->gemini = new GeminiProvider();
    }

    /** @test */
    public function it_connects_and_classifies_intent_via_real_gemini(): void
    {
        $result = $this->gemini->classifyIntent('eh yang tadi salah, kopi tadi 25rb bukan 20rb');
        
        $this->assertEquals(MessageIntent::Correction, $result->intent);
        $this->assertGreaterThanOrEqual(0.7, $result->confidence);
    }

    /** @test */
    public function it_extracts_transactions_via_real_gemini(): void
    {
        $categories = ['Makan & Minum', 'Transport', 'Belanja', 'Lainnya'];
        $results = $this->gemini->extractTransactions('beli nasi goreng 15rb dan grab 20k', $categories);

        $this->assertCount(2, $results);

        // First transaction (nasi goreng)
        $this->assertStringContainsString('nasi goreng', strtolower(trim($results[0]->description)));
        $this->assertEquals(15000, $results[0]->amount);
        $this->assertEquals(TransactionType::Expense, $results[0]->type);
        $this->assertEquals('Makan & Minum', $results[0]->categoryName);

        // Second transaction (grab)
        $this->assertEquals('grab', strtolower(trim($results[1]->description)));
        $this->assertEquals(20000, $results[1]->amount);
        $this->assertEquals(TransactionType::Expense, $results[1]->type);
        $this->assertEquals('Transport', $results[1]->categoryName);
    }

    /** @test */
    public function it_parses_financial_commands_via_real_gemini(): void
    {
        $context = [
            'recent_transactions' => [
                ['id' => 1, 'description' => 'kopi', 'amount' => 20000, 'category' => 'Makan & Minum', 'wallet' => 'Cash', 'date' => now()->toDateString()]
            ]
        ];

        $result = $this->gemini->parseFinancialCommand('yang kopi tadi harusnya 25rb', $context);

        $this->assertEquals('update_transaction', $result->action);
        $this->assertStringContainsString('kopi', strtolower($result->target['description'] ?? ''));
        $this->assertEquals(25000, $result->changes['amount'] ?? 0);
    }

    /** @test */
    public function it_parses_queries_via_real_gemini(): void
    {
        $result = $this->gemini->parseQuery('bulan ini habis berapa buat makan?');

        $this->assertEquals('total_by_category', $result->queryType);
        $this->assertEquals('this_month', $result->period);
        $this->assertNotNull($result->categoryFilter);
        $this->assertStringContainsString('makan', strtolower($result->categoryFilter));
    }

    /** @test */
    public function it_formats_response_via_real_gemini(): void
    {
        $data = [
            'transactions' => [
                ['description' => 'kopi', 'amount' => 20000, 'type' => 'expense', 'category' => 'Makan & Minum']
            ]
        ];

        $response = $this->gemini->formatResponse('transaction_confirmation', $data);

        $this->assertNotEmpty($response);
        $this->assertStringContainsString('kopi', strtolower($response));
        $this->assertStringContainsString('20.000', $response);
    }
}
