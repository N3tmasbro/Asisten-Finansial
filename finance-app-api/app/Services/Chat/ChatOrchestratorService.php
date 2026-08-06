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
    ) {}

    /**
     * Main orchestration method: process an incoming message through the 3-stage AI pipeline.
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

        // Log incoming message
        $chatMessage = ChatMessage::create([
            'user_id' => $user->id,
            'direction' => ChatDirection::Incoming,
            'body' => $dto->message,
            'wa_message_id' => $dto->messageId,
        ]);

        try {
            // Stage 1: Classify intent
            $intent = $this->aiProvider->classifyIntent($dto->message, [
                'user_name' => $user->name,
            ]);

            $chatMessage->update([
                'intent' => $intent->intent,
                'ai_raw_response' => $intent->toArray(),
            ]);

            // Route based on intent
            $response = match ($intent->intent) {
                MessageIntent::AddTransaction => $this->handleTransaction($user, $dto->message, $chatMessage),
                MessageIntent::QueryReport => $this->handleQuery($user, $dto->message),
                MessageIntent::Correction => $this->correctionHandler->handle($user, $dto->message, $chatMessage),
                MessageIntent::GreetingSmallTalk => $this->handleGreeting($user),
                MessageIntent::Unclear => $this->handleUnclear(),
            };

            $chatMessage->update(['processed_at' => now()]);

            // Log and send outgoing response
            ChatMessage::create([
                'user_id' => $user->id,
                'direction' => ChatDirection::Outgoing,
                'body' => $response,
                'intent' => $intent->intent,
            ]);

            $this->whatsAppProvider->sendMessage($replyTo, $response);

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

