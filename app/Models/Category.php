<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    protected $fillable = [
        'name', 'slug', 'icon', 'accent', 'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public static function activeCached()
    {
        return Cache::remember('relic.categories.active', 600, function () {
            return static::where('is_active', true)->orderBy('sort_order')->get();
        });
    }
}
