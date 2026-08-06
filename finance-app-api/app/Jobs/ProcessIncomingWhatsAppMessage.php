<?php

namespace App\Jobs;

use App\DTOs\IncomingMessageDTO;
use App\Services\Chat\ChatOrchestratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly IncomingMessageDTO $message,
    ) {}

    public function handle(ChatOrchestratorService $orchestrator): void
    {
        Log::info('Processing WhatsApp message', [
            'from' => $this->message->phoneNumber,
            'message' => $this->message->message,
        ]);

        $orchestrator->handle($this->message);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to process WhatsApp message', [
            'from' => $this->message->phoneNumber,
            'message' => $this->message->message,
            'error' => $exception->getMessage(),
        ]);
    }
}
