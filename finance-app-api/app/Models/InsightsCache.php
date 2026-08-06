<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsightsCache extends Model
{
    use HasFactory;

    protected $table = 'insights_cache';

    protected $fillable = [
        'user_id',
        'type',
        'period',
        'data',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Scope: get valid (non-expired) cache entries.
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Get or compute cached insight data.
     */
    public static function getOrCompute(int $userId, string $type, string $period, callable $compute, int $ttlMinutes = 60): array
    {
        $cached = static::where('user_id', $userId)
            ->where('type', $type)
            ->where('period', $period)
            ->valid()
            ->first();

        if ($cached) {
            return $cached->data;
        }

        $data = $compute();

        static::updateOrCreate(
            ['user_id' => $userId, 'type' => $type, 'period' => $period],
            ['data' => $data, 'expires_at' => now()->addMinutes($ttlMinutes)]
        );

        return $data;
    }
}
