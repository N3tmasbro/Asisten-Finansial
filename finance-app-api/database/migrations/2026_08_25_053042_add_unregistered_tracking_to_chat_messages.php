<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Track nomor pengirim (termasuk pengguna tidak terdaftar)
            $table->string('sender_phone', 30)->nullable()->after('user_id')->index();

            // Status user saat pesan masuk: 'registered' | 'unregistered'
            $table->string('user_status', 20)->nullable()->after('sender_phone')->index();

            // Kapan terakhir bot mengirim greeting ke nomor tidak terdaftar ini
            $table->timestamp('last_unregistered_reply_at')->nullable()->after('processed_at');

            // Composite index untuk query cepat per nomor + status
            $table->index(['sender_phone', 'user_status'], 'chat_messages_phone_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('chat_messages_phone_status_idx');
            $table->dropIndex(['sender_phone']);
            $table->dropIndex(['user_status']);
            $table->dropColumn(['sender_phone', 'user_status', 'last_unregistered_reply_at']);
        });
    }
};
