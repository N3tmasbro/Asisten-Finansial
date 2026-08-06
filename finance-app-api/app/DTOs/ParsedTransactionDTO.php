<?php

namespace App\DTOs;

use App\Enums\TransactionType;

class ParsedTransactionDTO
{
    public function __construct(
        public readonly string $description,
        public readonly int $amount,
        public readonly TransactionType $type,
        public readonly ?string $categoryName,
        public readonly float $confidence,
        public readonly ?string $walletName = null,
        public readonly ?string $transactionDate = null,
    ) {}

    public static function fromAIResponse(array $data): self
    {
        return new self(
            description: $data['description'] ?? '',
            amount: (int) ($data['amount'] ?? 0),
            type: TransactionType::tryFrom($data['type'] ?? 'expense') ?? TransactionType::Expense,
            categoryName: $data['category'] ?? null,
            confidence: (float) ($data['confidence'] ?? 0.0),
            walletName: $data['wallet'] ?? null,
            transactionDate: $data['date'] ?? null,
        );
    }

    public function isLowConfidence(): bool
    {
        return $this->confidence < 0.7;
    }

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'amount' => $this->amount,
            'type' => $this->type->value,
            'category_name' => $this->categoryName,
            'confidence' => $this->confidence,
            'wallet_name' => $this->walletName,
            'transaction_date' => $this->transactionDate,
        ];
    }
}
