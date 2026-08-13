<?php

namespace App\Console\Commands;

use App\Services\Analytics\RecurringReminderNotifierService;
use Illuminate\Console\Command;

class SendRecurringRemindersCommand extends Command
{
    protected $signature = 'reminder:send';
    protected $description = 'Send WhatsApp reminders for upcoming recurring expenses (runs daily at 08:00)';

    public function handle(RecurringReminderNotifierService $notifier): int
    {
        $this->info('Checking for due reminders...');

        $result = $notifier->sendDueReminders();

        $this->info("✓ Sent: {$result['sent']} | Skipped (no WA): {$result['skipped']}");

        return self::SUCCESS;
    }
}
