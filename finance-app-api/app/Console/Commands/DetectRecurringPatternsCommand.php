<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Analytics\RecurringPatternDetectorService;
use Illuminate\Console\Command;

class DetectRecurringPatternsCommand extends Command
{
    protected $signature = 'reminder:detect {--user= : Only process a specific user ID}';
    protected $description = 'Analyze transaction history and detect recurring expense patterns for all users';

    public function handle(RecurringPatternDetectorService $detector): int
    {
        $userId = $this->option('user');

        $users = $userId
            ? User::where('id', $userId)->get()
            : User::whereNotNull('wa_lid')->orWhereNotNull('phone_number')->get();

        if ($users->isEmpty()) {
            $this->info('No users found.');
            return self::SUCCESS;
        }

        $this->info("Scanning {$users->count()} user(s) for recurring patterns...");

        $totalDetected = 0;

        foreach ($users as $user) {
            $count = $detector->detectForUser($user->id);
            $totalDetected += $count;

            if ($count > 0) {
                $this->line("  ✓ User #{$user->id} ({$user->name}): {$count} pattern(s) found/updated.");
            }
        }

        $this->info("Done. Total patterns detected/updated: {$totalDetected}");

        return self::SUCCESS;
    }
}
