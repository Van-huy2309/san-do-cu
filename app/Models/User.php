<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'phone_verified_at',
        'google_id',
        'apple_id',
        'city',
        'district',
        'lat',
        'lng',
        'bio',
        'avatar_path',
        'is_seller',
        'seller_verified',
        'kyc_status',
        'kyc_id_last4',
        'kyc_full_name',
        'kyc_front_path',
        'kyc_back_path',
        'kyc_note',
        'kyc_reviewed_at',
        'wallet_balance',
        'wallet_frozen',
        'is_banned',
        'ban_reason',
        'banned_at',
        'rating_avg',
        'rating_count',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verify_expires_at' => 'datetime',
            'password' => 'hashed',
            'is_seller' => 'boolean',
            'seller_verified' => 'boolean',
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
            'wallet_balance' => 'integer',
            'wallet_frozen' => 'integer',
        ];
    }

    public function kycVerified(): bool
    {
        return $this->kyc_status === 'verified';
    }

    public function sendEmailVerificationNotification(): void
    {
        $code = app(\App\Services\EmailVerificationService::class)->issue($this);
        $this->notify(new \App\Notifications\VerifyEmail($code));
    }

    public function kycLabel(): string
    {
        return match ($this->kyc_status) {
            'verified' => 'Đã KYC',
            'pending' => 'Chờ duyệt CCCD',
            'rejected' => 'KYC bị từ chối',
            default => 'Chưa định danh',
        };
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'seller_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'customer_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function searchAlerts(): HasMany
    {
        return $this->hasMany(SearchAlert::class);
    }

    public function receivedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'seller_id');
    }

    public function shopUrl(): string
    {
        return route('shops.show', $this);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? 'R', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first . $last);
    }

    public function ratingScore(): float
    {
        $avg = $this->receivedReviews()->avg('rating');

        return $avg ? round((float) $avg, 1) : 0.0;
    }
}
