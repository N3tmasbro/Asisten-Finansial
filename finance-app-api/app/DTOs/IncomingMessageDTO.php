<?php

namespace App\DTOs;

class IncomingMessageDTO
{
    public function __construct(
        public readonly string $phoneNumber,
        public readonly string $message,
        public readonly int $timestamp,
        public readonly string $messageId,
        public readonly ?string $replyJid = null,
    ) {}

    public static function fromWebhookPayload(array $payload): self
    {
        return new self(
            phoneNumber: $payload['from'],
            message: $payload['message'],
            timestamp: $payload['timestamp'],
            messageId: $payload['message_id'],
            replyJid: $payload['reply_jid'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'phone_number' => $this->phoneNumber,
            'message' => $this->message,
            'timestamp' => $this->timestamp,
            'message_id' => $this->messageId,
            'reply_jid' => $this->replyJid,
        ];
    }
}
