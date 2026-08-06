<?php

namespace App\Models;

use App\Enums\ChatDirection;
use App\Enums\MessageIntent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'direction',
        'body',
        'intent',
        'ai_raw_response',
        'wa_message_id',
        'processed_at',
    ];

    protected $casts = [
        'direction' => ChatDirection::class,
        'intent' => MessageIntent::class,
        'ai_raw_response' => 'array',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Transactions created from this chat message (supports multi-transaction per message).
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope: incoming messages only.
     */
    public function scopeIncoming($query)
    {
        return $query->where('direction', ChatDirection::Incoming);
    }

    /**
     * Scope: outgoing messages only.
     */
    public function scopeOutgoing($query)
    {
        return $query->where('direction', ChatDirection::Outgoing);
    }
}
