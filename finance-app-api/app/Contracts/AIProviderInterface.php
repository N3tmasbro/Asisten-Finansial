<?php

namespace App\Contracts;

use App\DTOs\FinancialCommandDTO;
use App\DTOs\IntentClassificationDTO;
use App\DTOs\ParsedTransactionDTO;
use App\DTOs\QueryParametersDTO;

interface AIProviderInterface
{
    /**
     * Stage 1: Classify the intent of an incoming message.
     *
     * @param string $message The raw user message
     * @param array $context Additional context (user history, etc.)
     * @return IntentClassificationDTO
     */
    public function classifyIntent(string $message, array $context = []): IntentClassificationDTO;

    /**
     * Stage 2A: Extract transaction(s) from a message.
     * Always returns an array of ParsedTransactionDTO (even for single transactions).
     *
     * @param string $message The raw user message
     * @param array $categories Available categories (default + user custom)
     * @param array $context Additional context
     * @return ParsedTransactionDTO[]
     */
    public function extractTransactions(string $message, array $categories, array $context = []): array;

    /**
     * Stage 2B: Parse a query/report request into structured parameters.
     * AI does NOT answer the query — only determines what to query.
     *
     * @param string $message The raw user message
     * @param array $context Additional context
     * @return QueryParametersDTO
     */
    public function parseQuery(string $message, array $context = []): QueryParametersDTO;

    /**
     * Stage 2C: Parse a financial command (correction, deletion, management).
     * Returns a structured command that the backend validates and executes.
     * AI must NOT invent database IDs, balances, or transaction history.
     *
     * @param string $message The raw user message
     * @param array $context Recent transactions, wallet names, category names for resolution
     * @return FinancialCommandDTO
     */
    public function parseFinancialCommand(string $message, array $context = []): FinancialCommandDTO;

    /**
     * Stage 3: Format raw data into a natural Bahasa Indonesia response.
     * AI must NOT alter/invent any numbers — only format what's given.
     *
     * @param string $type The type of response (transaction_confirmation, query_result, etc.)
     * @param array $data The raw data from database
     * @param array $context Additional context
     * @return string Natural language response
     */
    public function formatResponse(string $type, array $data, array $context = []): string;
}

