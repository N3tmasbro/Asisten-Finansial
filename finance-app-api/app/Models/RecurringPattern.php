<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringPattern extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'description_pattern',
        'amount_avg',
        'amount_tolerance',
        'expected_day_of_month',
        'day_tolerance',
        'occurrences_count',
        'last_seen_date',
        'next_expected_date',
        'reminded_h3',
        'reminded_h1',
        'current_cycle_month',
        'is_active',
    ];

    protected $casts = [
        'amount_avg' => 'integer',
        'amount_tolerance' => 'integer',
        'expected_day_of_month' => 'integer',
        'day_tolerance' => 'integer',
        'occurrences_count' => 'integer',
        'last_seen_date' => 'date',
        'next_expected_date' => 'date',
        'current_cycle_month' => 'date',
        'reminded_h3' => 'boolean',
        'reminded_h1' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
