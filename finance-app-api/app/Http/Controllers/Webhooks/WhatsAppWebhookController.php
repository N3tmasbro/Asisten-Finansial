<?php

namespace App\Http\Controllers\Webhooks;

use App\DTOs\IncomingMessageDTO;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle incoming WhatsApp message from the bridge service.
     */
    public function handle(Request $request): JsonResponse
    {
        // Validate bridge secret
        $bridgeSecret = config('services.whatsapp_bridge.secret');
        if ($bridgeSecret && $request->input('bridge_secret') !== $bridgeSecret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'from' => 'required|string',
            'message' => 'required|string',
            'timestamp' => 'required|integer',
            'message_id' => 'required|string',
        ]);

        $dto = IncomingMessageDTO::fromWebhookPayload($request->all());

        // Dispatch async job for processing
        ProcessIncomingWhatsAppMessage::dispatch($dto);

        return response()->json(['status' => 'queued']);
    }
}
