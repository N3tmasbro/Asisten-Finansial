<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insights_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // summary, trend, prediction, etc.
            $table->string('period'); // 2026-08, 2026-Q3, etc.
            $table->json('data');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'type', 'period']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insights_cache');
    }
};
