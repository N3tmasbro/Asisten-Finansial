<?php

namespace App\Services\Chat;

use App\Contracts\AIProviderInterface;
use App\Contracts\WhatsAppProviderInterface;
use App\DTOs\IncomingMessageDTO;
use App\Enums\ChatDirection;
use App\Enums\MessageIntent;
use App\Models\ChatMessage;
use App\Models\User;
use App\Repositories\TransactionRepository;
use App\Services\Analytics\BalancePredictionService;
use App\Services\Analytics\SummaryService;
use App\Services\Analytics\TrendService;
use App\Services\Transaction\CategoryMatcherService;
use App\Services\Transaction\TransactionService;
use Illuminate\Support\Facades\Log;

class ChatOrchestratorService
{
    /** Confirmation keywords — user agrees */
    private const CONFIRM_YES = ['ya', 'iya', 'oke', 'yes', 'setuju', 'yup', 'bener', 'benar', 'ok', 'sip', 'sep'];

    /** Confirmation keywords — user cancels */
    private const CONFIRM_NO = ['tidak', 'no', 'cancel', 'batal', 'ga jadi', 'gjd', 'g jadi', 'dk jadi', 'urung', 'dak', 'idak', 'dk'];

    /** Max minutes before a pending action expires */
    private const PENDING_EXPIRY_MINUTES = 10;

    public function __construct(
        private AIProviderInterface $aiProvider,
        private WhatsAppProviderInterface $whatsAppProvider,
        private TransactionService $transactionService,
        private CategoryMatcherService $categoryMatcher,
        private TransactionRepository $transactionRepo,
        private SummaryService $summaryService,
        private TrendService $trendService,
        private BalancePredictionService $predictionService,
        private CorrectionHandlerService $correctionHandler,
        private DeleteHandlerService $deleteHandler,
        private InspectHandlerService $inspectHandler,
        private ManageRecordsHandlerService $manageHandler,
    ) {}

    /**
     * Main orchestration method: process an incoming message through the AI pipeline.
     *
     * Steps:
     * 0. Idempotency check (wa_message_id)
     * 1. Check pending confirmation (before AI)
     * 2. Check pending candidate selection (before AI)
     * 3. Log incoming message
     * 4. AI Intent Classification
     * 5. Route to handler
     * 6. Log outgoing message (with pending_* metadata if any)
     * 7. Send via WhatsApp
     */
    public function handle(IncomingMessageDTO $dto): void
    {
        // Find user by phone number or WhatsApp LID
        $user = $this->findUser($dto);
        $replyTo = $dto->replyJid ?? $dto->phoneNumber;

        if (!$user) {
            $this->whatsAppProvider->sendMessage(
                $replyTo,
                "Halo! 👋 Sepertinya kamu belum terdaftar. Daftar dulu di website kami ya! 🌐"
            );
            return;
        }

        // Step 0: Idempotency check — prevent duplicate processing of same WA message
        if ($this->isDuplicateMessage($dto->messageId)) {
            Log::info('Duplicate WA message ignored', ['message_id' => $dto->messageId]);
            return;
        }

        try {
            // Step 1: Check pending confirmation (before AI — saves API calls)
            $confirmationResult = $this->handlePendingConfirmation($user, $dto->message);
            if ($confirmationResult !== null) {
                $this->logAndSend($user, $dto, $confirmationResult, null);
                return;
            }

            // Step 2: Check pending candidate selection (before AI — saves API calls)
            $selectionResult = $this->handlePendingSelection($user, $dto->message);
            if ($selectionResult !== null) {
                $this->logAndSend($user, $dto, $selectionResult, null);
                return;
            }

            // Step 3: Log incoming message
            $chatMessage = ChatMessage::create([
                'user_id' => $user->id,
                'direction' => ChatDirection::Incoming,
                'body' => $dto->message,
                'wa_message_id' => $dto->messageId,
            ]);

            // Step 4: AI Intent Classification (1 Gemini call)
            $intent = $this->aiProvider->classifyIntent($dto->message, [
                'user_name' => $user->name,
            ]);

            $chatMessage->update([
                'intent' => $intent->intent,
                'ai_raw_response' => $intent->toArray(),
            ]);

            // Step 5: Route to handler
            $result = match ($intent->intent) {
                MessageIntent::AddTransaction => $this->handleTransaction($user, $dto->message, $chatMessage),
                MessageIntent::QueryReport => $this->handleQuery($user, $dto->message),
                MessageIntent::Correction => $this->correctionHandler->handle($user, $dto->message, $chatMessage),
                MessageIntent::DeleteTransaction => $this->deleteHandler->handle($user, $dto->message, $chatMessage),
                MessageIntent::InspectRecords => $this->inspectHandler->handle($user, $dto->message),
                MessageIntent::ManageRecords => $this->manageHandler->handle($user, $dto->message, $chatMessage),
                MessageIntent::GreetingSmallTalk => $this->handleGreeting($user),
                MessageIntent::Unclear => $this->handleUnclear(),
            };

            $chatMessage->update(['processed_at' => now()]);

            // Step 6 & 7: Log outgoing message and send
            $this->logAndSend($user, $dto, $result, $intent->intent);

        } catch (\Exception $e) {
            Log::error('Chat orchestration error', [
                'user_id' => $user->id,
                'message' => $dto->message,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorResponse = "Maaf, ada gangguan teknis 😓 Coba kirim ulang ya!";
            $this->whatsAppProvider->sendMessage($replyTo, $errorResponse);
        }
    }

    // ─────────────────────────────────────────────────────
    //  Step 0: Idempotency
    // ─────────────────────────────────────────────────────

    /**
     * Check if this WA message was already processed.
     */
    private function isDuplicateMessage(string $messageId): bool
    {
        return ChatMessage::where('wa_message_id', $messageId)
            ->where('direction', ChatDirection::Incoming)
            ->exists();
    }

    // ─────────────────────────────────────────────────────
    //  Step 1: Pending Confirmation
    // ─────────────────────────────────────────────────────

    /**
     * Check if there's a pending confirmation and handle the user's response.
     * Returns response string if handled, null if not a confirmation.
     */
    private function handlePendingConfirmation(User $user, string $message): ?string
    {
        $lastOutgoing = $this->getLastOutgoingMessage($user);
        if (!$lastOutgoing) {
            return null;
        }

        $pending = $lastOutgoing->ai_raw_response['pending_confirmation'] ?? null;
        if (!$pending) {
            return null;
        }

        // Check expiry
        $createdAt = $pending['created_at'] ?? null;
        $expiryMinutes = $pending['expires_minutes'] ?? self::PENDING_EXPIRY_MINUTES;
        if ($createdAt && now()->diffInMinutes($createdAt) > $expiryMinutes) {
            // Expired — clear and proceed normally
            $this->clearPendingMetadata($lastOutgoing);
            return null;
        }

        $messageLower = mb_strtolower(trim($message));

        // User confirms
        if ($this->matchesAnyKeyword($messageLower, self::CONFIRM_YES)) {
            $this->clearPendingMetadata($lastOutgoing);
            return $this->executePendingConfirmation($user, $pending);
        }

        // User cancels
        if ($this->matchesAnyKeyword($messageLower, self::CONFIRM_NO)) {
            $this->clearPendingMetadata($lastOutgoing);
            return "OK, dibatalkan 👍";
        }

        // Something else — clear pending and treat as new message
        $this->clearPendingMetadata($lastOutgoing);
        return null;
    }

    /**
     * Execute a confirmed pending action.
     */
    private function executePendingConfirmation(User $user, array $pending): string
    {
        return match ($pending['action'] ?? '') {
            'delete_transaction' => $this->deleteHandler->executeDelete(
                $user,
                $pending['transaction_id'] ?? 0
            ),
            default => "Aksi tidak dikenali 🤔",
        };
    }

    // ─────────────────────────────────────────────────────
    //  Step 2: Pending Candidate Selection
    // ─────────────────────────────────────────────────────

    /**
     * Check if there's a pending candidate selection and handle user's choice.
     * Returns response string/array if handled, null if not a selection.
     */
    private function handlePendingSelection(User $user, string $message): string|array|null
    {
        $lastOutgoing = $this->getLastOutgoingMessage($user);
        if (!$lastOutgoing) {
            return null;
        }

        $pending = $lastOutgoing->ai_raw_response['pending_selection'] ?? null;
        if (!$pending) {
            return null;
        }

        // Check expiry
        $expiryMinutes = $pending['expires_minutes'] ?? self::PENDING_EXPIRY_MINUTES;
        if (now()->diffInMinutes($lastOutgoing->created_at) > $expiryMinutes) {
            $this->clearPendingMetadata($lastOutgoing);
            return null;
        }

        $messageLower = mb_strtolower(trim($message));

        // Try to extract a number selection
        $selectedIndex = $this->extractSelectionNumber($messageLower);

        if ($selectedIndex !== null) {
            $candidates = $pending['candidates'] ?? [];
            if ($selectedIndex >= 0 && $selectedIndex < count($candidates)) {
                $selectedCandidate = $candidates[$selectedIndex];
                $this->clearPendingMetadata($lastOutgoing);

                return $this->executeSelectionAction(
                    $user,
                    $pending['action'] ?? '',
                    $selectedCandidate,
                    $pending['original_changes'] ?? null
                );
            }

            return "Nomor tidak valid 🤔 Pilih antara 1 sampai " . count($candidates);
        }

        // Not a number — clear pending and treat as new message
        $this->clearPendingMetadata($lastOutgoing);
        return null;
    }

    /**
     * Execute action on the selected candidate.
     */
    private function executeSelectionAction(User $user, string $action, array $candidate, ?array $changes): string|array
    {
        $transactionId = $candidate['id'] ?? 0;

        return match ($action) {
            'correction' => $this->correctionHandler->applyCorrectionById($user, $transactionId, $changes),
            'delete' => $this->deleteHandler->requestConfirmationById($user, $transactionId),
            default => "Aksi tidak dikenali 🤔",
        };
    }

    /**
     * Extract selection number from user message.
     * Handles: "1", "nomor 1", "no 1", "yang ke-1", "yang pertama", etc.
     */
    private function extractSelectionNumber(string $message): ?int
    {
        // Direct number: "1", "2", "3"
        if (preg_match('/^(\d+)$/', $message, $matches)) {
            return ((int) $matches[1]) - 1; // 0-indexed
        }

        // "nomor 1", "no 1", "no. 1", "ke-1", "ke 1"
        if (preg_match('/(?:nomor|no\.?|ke[- ]?)(\d+)/i', $message, $matches)) {
            return ((int) $matches[1]) - 1;
        }

        // Indonesian ordinals
        $ordinals = ['pertama' => 0, 'kedua' => 1, 'ketiga' => 2, 'keempat' => 3, 'kelima' => 4];
        foreach ($ordinals as $word => $index) {
            if (str_contains($message, $word)) {
                return $index;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────

    /**
     * Get the last outgoing message for this user.
     */
    private function getLastOutgoingMessage(User $user): ?ChatMessage
    {
        return ChatMessage::where('user_id', $user->id)
            ->where('direction', ChatDirection::Outgoing)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Clear pending metadata from an outgoing message.
     */
    private function clearPendingMetadata(ChatMessage $message): void
    {
        $rawResponse = $message->ai_raw_response ?? [];
        unset($rawResponse['pending_confirmation'], $rawResponse['pending_selection']);
        $message->update(['ai_raw_response' => $rawResponse ?: null]);
    }

    /**
     * Check if message matches any keyword in list.
     */
    private function matchesAnyKeyword(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($message === $keyword || str_starts_with($message, $keyword . ' ') || str_starts_with($message, $keyword . '.')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Log outgoing message and send via WhatsApp.
     * Handles both simple string responses and array responses with pending metadata.
     */
    private function logAndSend(User $user, IncomingMessageDTO $dto, string|array $result, ?MessageIntent $intent): void
    {
        $replyTo = $dto->replyJid ?? $dto->phoneNumber;

        // Extract response text and optional metadata
        $responseText = is_string($result) ? $result : ($result['text'] ?? '');
        $metadata = [];

        if (is_array($result)) {
            if (isset($result['pending_confirmation'])) {
                $metadata['pending_confirmation'] = $result['pending_confirmation'];
            }
            if (isset($result['pending_selection'])) {
                $metadata['pending_selection'] = $result['pending_selection'];
            }
        }

        // Log outgoing message with metadata
        ChatMessage::create([
            'user_id' => $user->id,
            'direction' => ChatDirection::Outgoing,
            'body' => $responseText,
            'intent' => $intent,
            'ai_raw_response' => !empty($metadata) ? $metadata : null,
        ]);

        $this->whatsAppProvider->sendMessage($replyTo, $responseText);
    }

    // ─────────────────────────────────────────────────────
    //  Existing Handlers (unchanged)
    // ─────────────────────────────────────────────────────

    /**
     * Handle transaction recording (Stage 2A → TransactionService → Stage 3).
     */
    private function handleTransaction(User $user, string $message, ChatMessage $chatMessage): string
    {
        // Get user's categories for AI prompt
        $categories = $this->categoryMatcher->getCategoryNamesForUser($user->id);

        // Stage 2A: Extract transactions
        $parsedTransactions = $this->aiProvider->extractTransactions($message, $categories, [
            'user_name' => $user->name,
        ]);

        if (empty($parsedTransactions)) {
            return "Hmm, aku nggak bisa baca transaksinya 🤔 Coba format: \"beli kopi 20rb\"";
        }

        // Save transactions with wallet balance update
        $transactions = $this->transactionService->createFromParsed($user, $parsedTransactions, $chatMessage);

        // Stage 3: Format response
        $txData = [
            'transactions' => array_map(fn($tx) => [
                'description' => $tx->description,
                'amount' => $tx->amount,
                'type' => $tx->type->value,
                'category' => $tx->category->name,
                'wallet' => $tx->wallet->name,
            ], $transactions),
            'low_confidence' => collect($parsedTransactions)->contains(fn($p) => $p->isLowConfidence()),
        ];

        return $this->aiProvider->formatResponse('transaction_confirmation', $txData);
    }

    /**
     * Handle financial query (Stage 2B → Analytics → Stage 3).
     */
    private function handleQuery(User $user, string $message): string
    {
        // Stage 2B: Parse query parameters
        $queryParams = $this->aiProvider->parseQuery($message);

        // Execute query based on type
        $data = match ($queryParams->queryType) {
            'total_by_category' => $this->summaryService->getCategoryBreakdown(
                $user->id,
                $queryParams->period ?? 'this_month'
            ),
            'total_by_period' => $this->summaryService->getSummary(
                $user->id,
                $queryParams->period ?? 'this_month'
            ),
            'trend_comparison' => $this->trendService->comparePeriods($user->id),
            'balance_prediction' => $this->predictionService->predict($user->id),
            'top_spending' => [
                'top_spending' => $this->transactionRepo->topSpending(
                    $user->id,
                    ...$this->summaryService->resolvePeriod($queryParams->period ?? 'this_month')
                )->map(fn($item) => [
                    'category' => $item->category->name ?? 'Unknown',
                    'total' => $item->total,
                    'count' => $item->count,
                ])->toArray(),
            ],
            default => $this->summaryService->getSummary($user->id, $queryParams->period ?? 'this_month'),
        };

        // If category filter, add filtered total
        if ($queryParams->categoryFilter) {
            [$from, $to] = $this->summaryService->resolvePeriod($queryParams->period ?? 'this_month');
            $data['category_filter'] = $queryParams->categoryFilter;
            $data['period_label'] = $data['period_label'] ?? ($queryParams->period ?? 'bulan ini');
        }

        // Stage 3: Format response
        return $this->aiProvider->formatResponse('query_result', $data);
    }

    private function handleGreeting(User $user): string
    {
        return $this->aiProvider->formatResponse('greeting', [
            'user_name' => $user->name,
        ]);
    }

    private function handleUnclear(): string
    {
        return $this->aiProvider->formatResponse('unclear', []);
    }

    /**
     * Find user by phone number or WhatsApp LID.
     * Auto-links LID to user on first match for future lookups.
     */
    private function findUser(IncomingMessageDTO $dto): ?User
    {
        $identifier = $dto->phoneNumber;

        // Try direct phone number match first
        $user = User::where('phone_number', $identifier)->first();
        if ($user) {
            // If we have a LID and user doesn't have one yet, save it
            if ($dto->replyJid && str_contains($dto->replyJid, '@lid') && !$user->wa_lid) {
                $lid = str_replace('@lid', '', $dto->replyJid);
                $user->update(['wa_lid' => $lid]);
                Log::info('Linked WA LID to user', ['user_id' => $user->id, 'lid' => $lid]);
            }
            return $user;
        }

        // Try LID match
        $user = User::where('wa_lid', $identifier)->first();
        if ($user) {
            return $user;
        }

        // The identifier might be a LID that we haven't seen before
        // For solo dev / small scale: auto-link to the first verified user
        if ($dto->replyJid && str_contains($dto->replyJid, '@lid')) {
            // Find users who have a verified phone number
            $user = User::whereHas('phoneVerifications', function ($q) {
                $q->whereNotNull('verified_at');
            })->first();

            // Fallback: just get the first user (solo dev scenario)
            if (!$user) {
                $user = User::first();
            }

            if ($user) {
                $user->update(['wa_lid' => $identifier]);
                Log::info('Auto-linked LID to user', ['user_id' => $user->id, 'lid' => $identifier]);
                return $user;
            }
        }

        return null;
    }
}
