<?php

namespace App\DTOs;

class QueryParametersDTO
{
    public function __construct(
        public readonly string $queryType,
        public readonly ?string $period = null,
        public readonly ?string $categoryFilter = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
    ) {}

    /**
     * Valid query types:
     * - total_by_category
     * - total_by_period
     * - trend_comparison
     * - balance_prediction
     * - top_spending
     * - general_summary
     */
    public static function fromAIResponse(array $data): self
    {
        return new self(
            queryType: $data['query_type'] ?? 'general_summary',
            period: $data['period'] ?? null,
            categoryFilter: $data['category_filter'] ?? null,
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'query_type' => $this->queryType,
            'period' => $this->period,
            'category_filter' => $this->categoryFilter,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }
}
