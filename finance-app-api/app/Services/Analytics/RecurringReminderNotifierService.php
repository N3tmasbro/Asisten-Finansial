<?php

namespace App\Services\Analytics;

use App\Contracts\WhatsAppProviderInterface;
use App\Models\RecurringPattern;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Sends proactive WhatsApp reminder messages for recurring expense patterns.
 *
 * Runs daily (via scheduler). Checks each active recurring pattern:
 * - If today == expected_day - 3 → send H-3 "early reminder"
 * - If today == expected_day - 1 → send H-1 "final reminder"
 *
 * Reminder flags (reminded_h3, reminded_h1) are reset each new billing cycle.
 */
class RecurringReminderNotifierService
{
    public function __construct(
        private WhatsAppProviderInterface $whatsApp,
    ) {}

    /**
     * Process all active recurring patterns and send reminders if due.
     *
     * @return array{sent: int, skipped: int}
     */
    public function sendDueReminders(): array
    {
        $today = Carbon::today();
        $sent = 0;
        $skipped = 0;

        $patterns = RecurringPattern::where('is_active', true)
            ->whereNotNull('next_expected_date')
            ->with('user')
            ->get();

        foreach ($patterns as $pattern) {
            $user = $pattern->user;

            // Skip users without a linked WA number
            if (empty($user->wa_lid) && empty($user->phone_number)) {
                $skipped++;
                continue;
            }

            $recipient = $user->wa_lid ?? $user->phone_number;

            $nextExpected = Carbon::parse($pattern->next_expected_date);

            // Reset reminder flags if we're in a new billing cycle
            $this->resetCycleIfNeeded($pattern, $nextExpected);

            $daysUntil = $today->diffInDays($nextExpected, false); // negative if past

            // H-3 reminder
            if ($daysUntil === 3 && !$pattern->reminded_h3) {
                $message = $this->buildH3Message($pattern);
                if ($this->whatsApp->sendMessage($recipient, $message)) {
                    $pattern->update(['reminded_h3' => true]);
                    $sent++;
                    Log::info('SmartReminder: H-3 sent', ['user_id' => $user->id, 'pattern' => $pattern->description_pattern]);
                }
            }

            // H-1 reminder
            elseif ($daysUntil === 1 && !$pattern->reminded_h1) {
                $message = $this->buildH1Message($pattern);
                if ($this->whatsApp->sendMessage($recipient, $message)) {
                    $pattern->update(['reminded_h1' => true]);
                    $sent++;
                    Log::info('SmartReminder: H-1 sent', ['user_id' => $user->id, 'pattern' => $pattern->description_pattern]);
                }
            }

            // Update next_expected_date if we've passed the due date (cycle completed)
            elseif ($daysUntil < -3) {
                $newExpected = $nextExpected->copy()->addMonth();
                $pattern->update([
                    'next_expected_date' => $newExpected->toDateString(),
                    'reminded_h3' => false,
                    'reminded_h1' => false,
                    'current_cycle_month' => $newExpected->copy()->startOfMonth()->toDateString(),
                ]);
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Reset per-cycle reminder flags if the stored cycle is outdated.
     */
    private function resetCycleIfNeeded(RecurringPattern $pattern, Carbon $nextExpected): void
    {
        $currentCycleMonth = $nextExpected->copy()->startOfMonth()->toDateString();

        if ($pattern->current_cycle_month?->format('Y-m') !== $nextExpected->format('Y-m')) {
            $pattern->update([
                'reminded_h3' => false,
                'reminded_h1' => false,
                'current_cycle_month' => $currentCycleMonth,
            ]);
        }
    }

    private function buildH3Message(RecurringPattern $pattern): string
    {
        $amount = number_format($pattern->amount_avg, 0, ',', '.');
        $description = ucwords($pattern->description_pattern);
        $dueDate = Carbon::parse($pattern->next_expected_date)->translatedFormat('j F Y');

        return "💡 Pengingat: biasanya kamu punya tagihan \"{$description}\" sekitar Rp{$amount} di tanggal {$dueDate}. Udah disiapkan dananya?";
    }

    private function buildH1Message(RecurringPattern $pattern): string
    {
        $amount = number_format($pattern->amount_avg, 0, ',', '.');
        $description = ucwords($pattern->description_pattern);

        return "⏰ Besok jatuh tempo \"{$description}\" ~Rp{$amount}. Jangan lupa bayar ya!";
    }
}
