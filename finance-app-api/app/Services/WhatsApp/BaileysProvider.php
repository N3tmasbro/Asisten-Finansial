<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp provider that communicates with the Baileys Node.js bridge service.
 */
class BaileysProvider implements WhatsAppProviderInterface
{
    private string $bridgeUrl;
    private string $bridgeSecret;

    public function __construct()
    {
        $this->bridgeUrl = config('services.whatsapp_bridge.url', 'http://localhost:3001');
        $this->bridgeSecret = config('services.whatsapp_bridge.secret', '');
    }

    public function sendMessage(string $phoneNumber, string $message): bool
    {
        try {
            $response = Http::post("{$this->bridgeUrl}/send", [
                'to' => $phoneNumber,
                'message' => $message,
                'bridge_secret' => $this->bridgeSecret,
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp message sent', ['to' => $phoneNumber]);
                return true;
            }

            Log::error('WhatsApp bridge error', [
                'to' => $phoneNumber,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp bridge exception', [
                'to' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendButtons(string $phoneNumber, string $message, array $buttons): bool
    {
        // Baileys supports interactive buttons, but for simplicity
        // we'll format them as text options for now
        $buttonText = $message . "\n\n";
        foreach ($buttons as $i => $label) {
            $buttonText .= ($i + 1) . ". {$label}\n";
        }

        return $this->sendMessage($phoneNumber, trim($buttonText));
    }

    public function sendPoll(string $phoneNumber, string $question, array $options): bool
    {
        try {
            $response = Http::post("{$this->bridgeUrl}/send", [
                'to' => $phoneNumber,
                'poll' => [
                    'name' => $question,
                    'options' => $options,
                ],
                'bridge_secret' => $this->bridgeSecret,
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp poll sent', ['to' => $phoneNumber]);
                return true;
            }

            Log::error('WhatsApp bridge poll error', [
                'to' => $phoneNumber,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp bridge poll exception', [
                'to' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getConnectionStatus(string $sessionId): string
    {
        try {
            $response = Http::get("{$this->bridgeUrl}/status", [
                'bridge_secret' => $this->bridgeSecret,
            ]);

            if ($response->successful()) {
                return $response->json('status', 'disconnected');
            }

            return 'disconnected';
        } catch (\Exception $e) {
            return 'disconnected';
        }
    }
}
