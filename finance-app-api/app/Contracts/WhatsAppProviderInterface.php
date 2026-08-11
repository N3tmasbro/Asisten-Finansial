<?php

namespace App\Contracts;

interface WhatsAppProviderInterface
{
    /**
     * Send a text message to a WhatsApp number.
     *
     * @param string $phoneNumber The recipient's phone number (e.g., "6281234567890")
     * @param string $message The message text
     * @return bool Whether the message was sent successfully
     */
    public function sendMessage(string $phoneNumber, string $message): bool;

    /**
     * Send a message with interactive buttons.
     *
     * @param string $phoneNumber The recipient's phone number
     * @param string $message The message text
     * @param array $buttons Array of button labels
     * @return bool Whether the message was sent successfully
     */
    public function sendButtons(string $phoneNumber, string $message, array $buttons): bool;

    /**
     * Send a poll message (interactive yes/no/options).
     *
     * @param string $phoneNumber The recipient's phone number or JID
     * @param string $question The poll question
     * @param array $options List of option texts
     * @return bool Whether the message was sent successfully
     */
    public function sendPoll(string $phoneNumber, string $question, array $options): bool;

    /**
     * Check the connection status of a WhatsApp session.
     *
     * @param string $sessionId The session identifier
     * @return string Status: 'connected', 'disconnected', 'connecting'
     */
    public function getConnectionStatus(string $sessionId): string;
}
