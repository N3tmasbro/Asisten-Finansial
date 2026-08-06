<?php

namespace App\DTOs;

use App\Enums\MessageIntent;

class IntentClassificationDTO
{
    public function __construct(
        public readonly MessageIntent $intent,
        public readonly float $confidence,
    ) {}

    public static function fromAIResponse(array $data): self
    {
        return new self(
            intent: MessageIntent::tryFrom($data['intent'] ?? 'unclear') ?? MessageIntent::Unclear,
            confidence: (float) ($data['confidence'] ?? 0.0),
        );
    }

    public function isConfident(): bool
    {
        return $this->confidence >= 0.7;
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent->value,
            'confidence' => $this->confidence,
        ];
    }
}
