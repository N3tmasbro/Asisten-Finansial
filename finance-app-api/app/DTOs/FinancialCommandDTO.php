<?php

namespace App\DTOs;

/**
 * Unified DTO for all structured AI financial commands.
 *
 * Used by correction, delete, and manage handlers.
 * The AI produces this structure; the backend validates and executes.
 */
class FinancialCommandDTO
{
    public function __construct(
        /** The action to perform (e.g. update_transaction, delete_transaction, create_wallet, etc.) */
        public readonly string $action,
        /** Hints to identify an existing record — NO database IDs. Used for correction/delete. */
        public readonly ?array $target = null,
        /** Fields to change on the target record. Used for corrections. */
        public readonly ?array $changes = null,
        /** Data for create/rename operations (name, type, amount, etc.) */
        public readonly ?array $data = null,
        /** AI confidence score 0.0–1.0 */
        public readonly float $confidence = 0.0,
        /** Original user message for audit */
        public readonly string $rawMessage = '',
    ) {}

    public static function fromAIResponse(array $data, string $rawMessage = ''): self
    {
        return new self(
            action: $data['action'] ?? 'unknown',
            target: $data['target'] ?? null,
            changes: $data['changes'] ?? null,
            data: $data['data'] ?? null,
            confidence: (float) ($data['confidence'] ?? 0.0),
            rawMessage: $rawMessage,
        );
    }

    /**
     * Get a target hint value, or null if not set.
     */
    public function targetHint(string $key): mixed
    {
        return $this->target[$key] ?? null;
    }

    /**
     * Get a change value, or null if not set.
     */
    public function change(string $key): mixed
    {
        return $this->changes[$key] ?? null;
    }

    /**
     * Get a data value, or null if not set.
     */
    public function dataValue(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Check if the command has any changes specified.
     */
    public function hasChanges(): bool
    {
        if (!$this->changes) {
            return false;
        }

        return count(array_filter($this->changes, fn($v) => $v !== null)) > 0;
    }

    /**
     * Check if this is a low-confidence command that might need clarification.
     */
    public function isLowConfidence(): bool
    {
        return $this->confidence < 0.6;
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'target' => $this->target,
            'changes' => $this->changes,
            'data' => $this->data,
            'confidence' => $this->confidence,
        ];
    }
}
