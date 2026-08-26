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
use App\Services\Analytics\SavingsAdviceService;
use App\Services\Analytics\SummaryService;
use App\Services\Analytics\TrendService;
use App\Services\Transaction\CategoryMatcherService;
use App\Services\Transaction\TransactionService;
use Illuminate\Support\Facades\Log;

class ChatOrchestratorService
{
    /** Confirmation keywords — user agrees */
    private const CONFIRM_YES = ['ya', 'iya', 'oke', 'yes', 'setuju', 'yup', 'bener', 'benar', 'ok', 'sip', 'sep', 'ya hapus semua', 'ya hapus', 'hapus semua'];

    /** Confirmation keywords — user cancels */
    private const CONFIRM_NO = ['tidak', 'no', 'cancel', 'batal', 'ga jadi', 'gjd', 'g jadi', 'dk jadi', 'urung', 'dak', 'idak', 'dk'];

    /** Max minutes before a pending action expires */
    private const PENDING_EXPIRY_MINUTES = 10;

    /** Greeting message sent once per 24h to unregistered senders */
    private const UNREGISTERED_GREETING =
        "Halo gan/sis! 👋 Lu belum terdaftar di database kami nih.\n\n" .
        "Bot ini adalah asisten keuangan pribadi yang ngerti chat biasa soal duit. Contoh:\n\n" .
        "💸 \"Beli kopi 20rb\" → langsung kerekam\n" .
        "📊 \"Gue udah abis berapa sih?\" → AI jawab\n" .
        "⏳ \"Kapan saldo gue habis?\" → AI prediksi\n\n" .
        "Fitur:\n" .
        "💬 Chat santai, AI yang parse (tanpa isi form ribet)\n" .
        "📊 Laporan tren pengeluaran\n" .
        "🤖 Saran hemat dari AI\n" .
        "💰 Prediksi ketahanan saldo\n" .
        "📱 Semua langsung lewat WhatsApp\n\n" .
        "Mau coba? Daftar gratis di:\nhttps://savings.marridho.tech\n(30 detik doang! ⚡)";

    public function __construct(
        private AIProviderInterface $aiProvider,
        private WhatsAppProviderInterface $whatsAppProvider,
        private TransactionService $transactionService,
        private CategoryMatcherService $categoryMatcher,
        private TransactionRepository $transactionRepo,
        private SummaryService $summaryService,
        private TrendService $trendService,
        private BalancePredictionService $predictionService,
        private SavingsAdviceService $savingsAdviceService,
        private CorrectionHandlerService $correctionHandler,
        private DeleteHandlerService $deleteHandler,
        private InspectHandlerService $inspectHandler,
        private ManageRecordsHandlerService $manageHandler,
        private UnregisteredUserService $unregisteredUserService,
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
            $this->handleUnregisteredUser($dto);
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
                MessageIntent::SavingsAdvice => $this->handleSavingsAdvice($user),
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
            'delete_all_transactions' => $this->deleteHandler->executeDeleteAll($user),
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
        $candidates = $pending['candidates'] ?? [];

        // Try to extract selection number(s)
        $selectedIndices = $this->extractSelectionNumbers($messageLower, count($candidates));

        if ($selectedIndices !== null) {
            $this->clearPendingMetadata($lastOutgoing);

            // Single selection — original behavior
            if (count($selectedIndices) === 1) {
                $selectedCandidate = $candidates[$selectedIndices[0]];
                return $this->executeSelectionAction(
                    $user,
                    $pending['action'] ?? '',
                    $selectedCandidate,
                    $pending['original_changes'] ?? null
                );
            }

            // Multi-selection — bulk action (currently only delete supports this)
            $action = $pending['action'] ?? '';
            if ($action === 'delete') {
                return $this->executeBulkDelete($user, $candidates, $selectedIndices);
            }

            // For other actions, process first item only
            $selectedCandidate = $candidates[$selectedIndices[0]];
            return $this->executeSelectionAction(
                $user,
                $action,
                $selectedCandidate,
                $pending['original_changes'] ?? null
            );
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
     * Execute bulk deletion of multiple selected candidates.
     */
    private function executeBulkDelete(User $user, array $candidates, array $selectedIndices): string
    {
        $deleted = [];
        $failed = [];

        foreach ($selectedIndices as $index) {
            $candidate = $candidates[$index] ?? null;
            if (!$candidate) continue;

            $transactionId = $candidate['id'] ?? 0;
            try {
                $result = $this->deleteHandler->executeDelete($user, $transactionId);
                $deleted[] = $candidate['description'] ?? "#{$transactionId}";
            } catch (\Exception $e) {
                $failed[] = $candidate['description'] ?? "#{$transactionId}";
            }
        }

        $response = '';
        if (!empty($deleted)) {
            $count = count($deleted);
            $response .= "✅ {$count} transaksi berhasil dihapus:\n";
            foreach ($deleted as $desc) {
                $response .= "  • {$desc}\n";
            }
        }

        if (!empty($failed)) {
            $response .= "\n❌ Gagal menghapus: " . implode(', ', $failed);
        }

        return trim($response) ?: "Tidak ada transaksi yang dihapus.";
    }

    /**
     * Extract selection number(s) from user message.
     * Handles: "1", "nomor 1", "1-5", "1,3,5", "semua", "yang pertama", etc.
     * Returns array of 0-indexed indices, or null if no selection detected.
     */
    private function extractSelectionNumbers(string $message, int $candidateCount): ?array
    {
        // "semua" / "all" / "semuanya"
        if (preg_match('/^(semua|semuanya|all)$/i', $message)) {
            return range(0, $candidateCount - 1);
        }

        // Range: "1-5", "1 - 5", "1 sampai 5"
        if (preg_match('/^(\d+)\s*[-–—]\s*(\d+)$/', $message, $matches)
            || preg_match('/^(\d+)\s+sampai\s+(\d+)$/i', $message, $matches)) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];
            if ($start >= 1 && $end >= $start && $end <= $candidateCount) {
                return range($start - 1, $end - 1);
            }
        }

        // Comma-separated: "1,3,5" or "1, 3, 5"
        if (preg_match('/^\d+(\s*,\s*\d+)+$/', $message)) {
            $nums = array_map('intval', preg_split('/\s*,\s*/', $message));
            $indices = [];
            foreach ($nums as $n) {
                if ($n >= 1 && $n <= $candidateCount) {
                    $indices[] = $n - 1;
                }
            }
            return !empty($indices) ? $indices : null;
        }

        // Direct single number: "1", "2", "3"
        if (preg_match('/^(\d+)$/', $message, $matches)) {
            $n = (int) $matches[1];
            if ($n >= 1 && $n <= $candidateCount) {
                return [$n - 1];
            }
            return null;
        }

        // "nomor 1", "no 1", "no. 1", "ke-1", "ke 1"
        if (preg_match('/(?:nomor|no\.?|ke[- ]?)(\d+)/i', $message, $matches)) {
            $n = (int) $matches[1];
            if ($n >= 1 && $n <= $candidateCount) {
                return [$n - 1];
            }
            return null;
        }

        // Indonesian ordinals
        $ordinals = ['pertama' => 0, 'kedua' => 1, 'ketiga' => 2, 'keempat' => 3, 'kelima' => 4];
        foreach ($ordinals as $word => $index) {
            if (str_contains($message, $word) && $index < $candidateCount) {
                return [$index];
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

        // Guard: never send empty or raw '{}' to users
        if (empty($responseText) || $responseText === '{}') {
            $responseText = "Maaf, ada gangguan teknis 😓 Coba kirim ulang ya!";
        }

        if (is_array($result)) {
            if (isset($result['pending_confirmation'])) {
                $metadata['pending_confirmation'] = $result['pending_confirmation'];
            }
            if (isset($result['pending_selection'])) {
                $metadata['pending_selection'] = $result['pending_selection'];
            }
        }

        // Append keyword tips if not present and not an interactive poll
        if (!str_contains($responseText, '💡 *Contoh') && !isset($result['pending_confirmation'])) {
            $tips = $this->getHelpfulTipsFooter($intent);
            $responseText .= "\n\n" . $tips;
        }

        // Log outgoing message with metadata
        ChatMessage::create([
            'user_id' => $user->id,
            'direction' => ChatDirection::Outgoing,
            'body' => $responseText,
            'intent' => $intent,
            'ai_raw_response' => !empty($metadata) ? $metadata : null,
        ]);

        if (is_array($result) && isset($result['pending_confirmation'])) {
            // Try sending as an interactive Poll confirmation
            $pollSent = $this->whatsAppProvider->sendPoll($replyTo, $responseText, ['Ya', 'Tidak']);
            if (!$pollSent) {
                // Fallback to regular text message if poll fails
                $this->whatsAppProvider->sendMessage($replyTo, $responseText . "\n\nBalas 'ya' atau 'tidak'.");
            }
        } else {
            $this->whatsAppProvider->sendMessage($replyTo, $responseText);
        }
    }

    /**
     * Generate a short keyword tips footer to make it easier for users to interact.
     */
    private function getHelpfulTipsFooter(?MessageIntent $intent): string
    {
        $sampleSets = [
            'transaction' => [
                '_Bulan ini habis berapa?_',
                '_Koreksi yang tadi jadi 25rb_',
                '_Cek dompet_',
            ],
            'query' => [
                '_Pengeluaran makan bulan ini_',
                '_Bandingkan bulan ini sama bulan lalu_',
                '_Kapan saldo gue habis?_',
            ],
            'manage' => [
                '_Buat wallet GoPay_',
                '_Buat budget Makan 1jt_',
                '_Cek dompet_',
            ],
            'inspect' => [
                '_Beli nasi goreng 15rb_',
                '_Cek budget_',
                '_Bulan ini habis berapa?_',
            ],
            'default' => [
                '_Beli kopi 20rb_',
                '_Bulan ini habis berapa?_',
                '_Cek dompet_',
            ],
        ];

        $key = match ($intent) {
            MessageIntent::AddTransaction  => 'transaction',
            MessageIntent::QueryReport     => 'query',
            MessageIntent::ManageRecords   => 'manage',
            MessageIntent::InspectRecords  => 'inspect',
            default                        => 'default',
        };

        $tips = implode("\n", array_map(fn($l) => "  {$l}", $sampleSets[$key]));
        return "\n〰️〰️〰️\n💡 *Coba juga:*\n{$tips}";
    }

    // ─────────────────────────────────────────────────────
    //  Unregistered User Handler
    // ─────────────────────────────────────────────────────

    /**
     * Handle incoming message from an unregistered sender.
     *
     * Rate limit: send greeting only once per 24h per phone number.
     * - First contact or expired window → send greeting & record timestamp
     * - Within 24h window              → silently ignore (log only)
     */
    private function handleUnregisteredUser(IncomingMessageDTO $dto): void
    {
        $phone   = $dto->phoneNumber;
        $replyTo = $dto->replyJid ?? $phone;

        $action = $this->unregisteredUserService->shouldSendGreeting($phone);

        if ($action === 'ignore') {
            $this->unregisteredUserService->recordIgnoredMessage($phone, $dto->message);
            return;
        }

        // $action === 'send'
        // IMPORTANT: Record FIRST, then send. This prevents a race condition where
        // the greeting is sent but the DB record fails (e.g. schema error), causing
        // every subsequent message to also trigger a greeting (infinite loop).
        try {
            $this->unregisteredUserService->recordGreetingSent($phone, $dto->message);
            $this->whatsAppProvider->sendMessage($replyTo, self::UNREGISTERED_GREETING);
        } catch (\Exception $e) {
            Log::error('[UnregisteredUser] Failed to send greeting', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
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
            return "Hmm, aku nggak bisa baca transaksinya 🤔\n\nCoba format yang lebih jelas:\n_makan siang 35rb_\n_gajian 5 juta_\n_bensin 50rb_";
        }

        // Check if ANY transactions need clarification (e.g. no amount given)
        $needsClarification = collect($parsedTransactions)->first(fn($p) => $p->needsClarification);
        if ($needsClarification) {
            $reason = $needsClarification->clarificationReason ?? 'nominal tidak disebutkan';
            $desc   = $needsClarification->description ?? $message;
            return "Aku nangkep *{$desc}*, tapi {$reason} 🤔\n\nSebutkan nominal-nya ya, contoh:\n_\"{$desc} 50rb\"_";
        }

        // Save transactions with wallet balance update
        $transactions = $this->transactionService->createFromParsed($user, $parsedTransactions, $chatMessage);

        // Stage 3: Format response
        $txData = [
            'transactions' => array_map(fn($tx) => [
                'description' => $tx->description,
                'amount'      => $tx->amount,
                'type'        => $tx->type->value,
                'category'    => $tx->category->name,
                'wallet'      => $tx->wallet->name,
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

    private function handleSavingsAdvice(User $user): string
    {
        $advice = $this->savingsAdviceService->generateAdvice($user->id);

        return $this->aiProvider->formatResponse('savings_advice', $advice);
    }

    private function handleGreeting(User $user): string
    {
        return $this->aiProvider->formatResponse('greeting', [
            'user_name' => $user->name,
        ]);
    }

    private function handleUnclear(): string
    {
        return implode("\n", [
            "Hmm, aku kurang paham maksudnya 🤔",
            "",
            "Coba salah satu perintah ini:",
            "  _beli kopi 20rb_ — catat pengeluaran",
            "  _gajian 5jt_ — catat pemasukan",
            "  _cek dompet_ — lihat saldo",
            "  _cek transaksi_ — riwayat transaksi",
            "  _cek budget_ — status budget",
            "  _bulan ini habis berapa?_ — laporan",
            "  _hapus transaksi terakhir_ — hapus",
        ]);
    }

    /**
     * Find user by phone number or WhatsApp LID.
     *
     * Security rules:
     * - Phone number match is ONLY accepted if the user has a verified phone number.
     * - LID match is accepted if a user already has that LID saved.
     * - Unknown numbers/LIDs are REJECTED — no fallback to "first user".
     * - Auto-links a verified user's LID on first contact for future lookups.
     */
    private function findUser(IncomingMessageDTO $dto): ?User
    {
        $identifier = $dto->phoneNumber;

        // 1. Try direct phone number match — must be a registered AND verified user
        $user = User::where('phone_number', $identifier)
            ->whereHas('phoneVerifications', function ($q) {
                $q->whereNotNull('verified_at');
            })
            ->first();

        if ($user) {
            // Auto-link LID on first contact so future messages via LID still work
            if ($dto->replyJid && str_contains($dto->replyJid, '@lid') && !$user->wa_lid) {
                $lid = str_replace('@lid', '', $dto->replyJid);
                $user->update(['wa_lid' => $lid]);
                Log::info('Linked WA LID to verified user', ['user_id' => $user->id, 'lid' => $lid]);
            }
            return $user;
        }

        // 2. Try LID match — only if this LID is already tied to a known user
        if (!empty($identifier)) {
            $user = User::where('wa_lid', $identifier)->first();
            if ($user) {
                return $user;
            }
        }

        // 3. No match — reject the sender entirely.
        // We intentionally do NOT fall back to User::first() or any other user.
        Log::warning('WA message from unrecognized / unverified sender', [
            'identifier' => $identifier,
            'reply_jid'  => $dto->replyJid,
        ]);

        return null;
    }
}
