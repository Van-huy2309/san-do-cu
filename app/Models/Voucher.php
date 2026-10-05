<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = [
        'seller_id', 'code', 'name', 'discount_type', 'discount_value',
        'min_order', 'quantity', 'used_count', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ends_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    public function isPlatform(): bool
    {
        return $this->seller_id === null;
    }

    public function remaining(): int
    {
        return max(0, (int) $this->quantity - (int) $this->used_count);
    }

    public function discountLabel(): string
    {
        return $this->discount_type === 'percent'
            ? $this->discount_value.'%'
            : number_format((int) $this->discount_value, 0, ',', '.').' ₫';
    }
};
