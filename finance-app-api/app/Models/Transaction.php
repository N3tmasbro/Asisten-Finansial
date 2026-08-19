<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'category_id',
        'chat_message_id',
        'type',
        'amount',
        'description',
        'notes',
        'raw_input',
        'transaction_date',
        'ai_confidence',
        'is_reviewed',
        'corrected_at',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'amount' => 'integer',
        'transaction_date' => 'date',
        'ai_confidence' => 'float',
        'is_reviewed' => 'boolean',
        'corrected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }

    /**
     * Get the formatted amount in Rupiah.
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp' . number_format($this->amount, 0, ',', '.');
    }

    /**
     * Check if this transaction needs user review (low AI confidence).
     */
    public function needsReview(): bool
    {
        return !$this->is_reviewed;
    }

    /**
     * Scope: transactions needing review.
     */
    public function scopeNeedsReview($query)
    {
        return $query->where('is_reviewed', false);
    }

    /**
     * Scope: transactions for a specific period.
     */
    public function scopeInPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('transaction_date', [$from, $to]);
    }

    /**
     * Scope: only expenses.
     */
    public function scopeExpenses($query)
    {
        return $query->where('type', TransactionType::Expense);
    }

    /**
     * Scope: only income.
     */
    public function scopeIncome($query)
    {
        return $query->where('type', TransactionType::Income);
    }
}
