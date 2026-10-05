<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingEnrollment extends Model
{
    protected $fillable = ['marketing_package_id', 'listing_id', 'seller_id', 'ends_at'];

    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MarketingPackage::class, 'marketing_package_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function scopeRunning($query)
    {
        return $query->where('ends_at', '>', now())
            ->whereHas('package', fn ($package) => $package->where('is_active', true));
    }
};
