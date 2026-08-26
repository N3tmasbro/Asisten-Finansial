<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-014 fix: Make user_id nullable in chat_messages table.
 *
 * Unregistered senders have no user account, so we need to allow NULL user_id
 * for rate-limiting records (user_status = 'unregistered'). Without this,
 * the INSERT in UnregisteredUserService::recordGreetingSent() always fails
 * with "Field 'user_id' doesn't have a default value", causing the bot to
 * send the greeting on every single message (rate-limiter never records).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
