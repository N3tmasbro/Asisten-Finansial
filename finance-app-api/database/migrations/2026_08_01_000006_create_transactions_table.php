<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->foreignId('chat_message_id')->nullable()->constrained()->onDelete('set null');
            $table->string('type'); // expense, income
            $table->bigInteger('amount'); // stored in Rupiah (smallest unit)
            $table->string('description')->nullable();
            $table->text('raw_input')->nullable(); // original user text for AI audit
            $table->date('transaction_date');
            $table->float('ai_confidence')->nullable();
            $table->boolean('is_reviewed')->default(true); // false = needs user review
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'category_id']);
            $table->index('chat_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
