<?php

namespace App\Models;

use App\Enums\SubscriptionTier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'wa_lid',
        'phone_verified_at',
        'password',
        'subscription_tier',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'subscription_tier' => SubscriptionTier::class,
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function whatsappSession(): HasOne
    {
        return $this->hasOne(WhatsAppSession::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function phoneVerifications(): HasMany
    {
        return $this->hasMany(PhoneVerification::class);
    }

    /**
     * Get the default wallet for this user.
     */
    public function defaultWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('is_default', true);
    }

    /**
     * Get all available categories (global defaults + user custom).
     */
    public function availableCategories()
    {
        return Category::where('user_id', $this->id)
            ->orWhere(function ($query) {
                $query->whereNull('user_id')->where('is_default', true);
            })
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Check if the user's phone number is verified.
     */
    public function isPhoneVerified(): bool
    {
        return $this->phoneVerifications()
            ->whereNotNull('verified_at')
            ->exists();
    }
}
