<?php

namespace App\Models;

use App\Enums\WalletType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'balance',
        'is_default',
    ];

    protected $casts = [
        'type' => WalletType::class,
        'balance' => 'integer',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the formatted balance in Rupiah.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return 'Rp' . number_format($this->balance, 0, ',', '.');
    }

    /**
     * Adjust balance by amount (positive for income, negative for expense).
     */
    public function adjustBalance(int $amount): void
    {
        $this->increment('balance', $amount);
    }
}
