<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Log;

/**
 * Handles rate limiting for unregistered WhatsApp users.
 *
 * Logic:
 * - First message from unknown number  → 'send' greeting
 * - Repeat message within 24h window   → 'ignore' (no reply, minimal log)
 * - Message after 24h window expires   → 'send' greeting again (new cycle)
 */
class UnregisteredUserService
{
    private const RATE_LIMIT_HOURS = 24;

    /**
     * Determine whether the bot should send a greeting to this unregistered sender.
     *
     * @return string 'send' | 'ignore'
     */
    public function shouldSendGreeting(string $phoneNumber): string
    {
        $lastEntry = ChatMessage::where('sender_phone', $phoneNumber)
            ->where('user_status', 'unregistered')
            ->whereNotNull('last_unregistered_reply_at')
            ->orderByDesc('last_unregistered_reply_at')
            ->first();

        // First time we've seen this number → send greeting
        if (!$lastEntry) {
            Log::info('[UnregisteredUser] First contact — will send greeting', [
                'phone' => $phoneNumber,
            ]);
            return 'send';
        }

        $lastGreetingAt = $lastEntry->last_unregistered_reply_at;
        $windowExpiry   = $lastGreetingAt->copy()->addHours(self::RATE_LIMIT_HOURS);

        if (now()->lt($windowExpiry)) {
            Log::info('[UnregisteredUser] Within 24h rate-limit window — ignoring', [
                'phone'           => $phoneNumber,
                'last_greeting'   => $lastGreetingAt->toISOString(),
                'window_expires'  => $windowExpiry->toISOString(),
            ]);
            return 'ignore';
        }

        // Window has expired → allow a new greeting cycle
        Log::info('[UnregisteredUser] 24h window expired — will send greeting again', [
            'phone'         => $phoneNumber,
            'last_greeting' => $lastGreetingAt->toISOString(),
        ]);
        return 'send';
    }

    /**
     * Record that a greeting was just sent to this unregistered number.
     * Creates a new log entry with the current timestamp.
     */
    public function recordGreetingSent(string $phoneNumber, string $rawMessage): void
    {
        ChatMessage::create([
            'sender_phone'                => $phoneNumber,
            'user_status'                 => 'unregistered',
            'direction'                   => 'incoming',
            'body'                        => $rawMessage,
            'last_unregistered_reply_at'  => now(),
        ]);

        Log::info('[UnregisteredUser] Greeting recorded', [
            'phone'     => $phoneNumber,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log an ignored message (within 24h window).
     * Does NOT update last_unregistered_reply_at — keeps the original greeting timestamp intact.
     */
    public function recordIgnoredMessage(string $phoneNumber, string $rawMessage): void
    {
        // Only log to application log — avoid unnecessary DB writes for ignored spam
        Log::info('[UnregisteredUser] Message ignored (within 24h window)', [
            'phone'   => $phoneNumber,
            'message' => mb_strimwidth($rawMessage, 0, 100, '…'),
        ]);
    }
}
