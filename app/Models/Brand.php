<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = ['name', 'slug', 'price_multiplier'];

    protected function casts(): array
    {
        return ['price_multiplier' => 'decimal:2'];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
