<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->bigInteger('amount'); // budget limit in Rupiah
            $table->string('period_type')->default('monthly'); // monthly, weekly
            $table->date('period_start')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'category_id', 'period_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
