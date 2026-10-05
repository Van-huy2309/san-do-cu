<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingPackage extends Model
{
    protected $fillable = ['name', 'description', 'slot_limit', 'duration_days', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(MarketingEnrollment::class);
    }

    public function remainingSlots(): int
    {
        $taken = $this->taken_slots ?? $this->enrollments()->running()->count();

        return max(0, (int) $this->slot_limit - (int) $taken);
    }
};
