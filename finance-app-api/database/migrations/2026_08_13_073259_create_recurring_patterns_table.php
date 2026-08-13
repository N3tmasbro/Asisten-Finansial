<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // Pattern fingerprint
            $table->string('description_pattern');          // Cleaned/normalized description
            $table->unsignedInteger('amount_avg');           // Rolling average amount
            $table->unsignedInteger('amount_tolerance');     // ±tolerance in Rupiah

            // Schedule detection
            $table->unsignedTinyInteger('expected_day_of_month'); // e.g. 5
            $table->unsignedTinyInteger('day_tolerance')->default(3); // ±3 days window

            // Tracking
            $table->unsignedTinyInteger('occurrences_count')->default(0);
            $table->date('last_seen_date')->nullable();
            $table->date('next_expected_date')->nullable();

            // Reminder tracking
            $table->boolean('reminded_h3')->default(false); // H-3 sent this cycle
            $table->boolean('reminded_h1')->default(false); // H-1 sent this cycle
            $table->date('current_cycle_month')->nullable(); // YYYY-MM-01 of the cycle

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['next_expected_date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_patterns');
    }
};
