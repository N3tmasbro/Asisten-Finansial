<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'icon',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'type' => TransactionType::class,
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

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Scope: only global default categories.
     */
    public function scopeDefaults($query)
    {
        return $query->whereNull('user_id')->where('is_default', true);
    }

    /**
     * Scope: categories for a specific user (custom + defaults).
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)
            ->orWhere(function ($q) {
                $q->whereNull('user_id')->where('is_default', true);
            });
    }

    /**
     * Scope: filter by transaction type.
     */
    public function scopeOfType($query, TransactionType $type)
    {
        return $query->where('type', $type);
    }
}
