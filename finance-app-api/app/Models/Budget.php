<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'period_type',
        'period_start',
    ];

    protected $casts = [
        'amount' => 'integer',
        'period_start' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the formatted budget amount in Rupiah.
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp' . number_format($this->amount, 0, ',', '.');
    }

    /**
     * Calculate how much of the budget has been used in the current period.
     */
    public function getUsedAmount(): int
    {
        $now = now();

        if ($this->period_type === 'monthly') {
            $from = $now->startOfMonth()->toDateString();
            $to = $now->endOfMonth()->toDateString();
        } else {
            $from = $now->startOfWeek()->toDateString();
            $to = $now->endOfWeek()->toDateString();
        }

        return $this->user->transactions()
            ->where('category_id', $this->category_id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');
    }

    /**
     * Get usage percentage.
     */
    public function getUsagePercentage(): float
    {
        if ($this->amount <= 0) {
            return 0;
        }

        return min(100, ($this->getUsedAmount() / $this->amount) * 100);
    }
}
