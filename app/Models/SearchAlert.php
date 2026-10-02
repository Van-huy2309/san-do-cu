<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchAlert extends Model
{
    protected $fillable = [
        'user_id', 'keyword', 'category_id', 'min_price', 'max_price', 'hits', 'last_hit_at',
    ];

    protected function casts(): array
    {
        return [
            'last_hit_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function matchesListing(Listing $listing): bool
    {
        if ($this->category_id && (int) $listing->category_id !== (int) $this->category_id) {
            return false;
        }
        if ($this->min_price !== null && (int) $listing->price < (int) $this->min_price) {
            return false;
        }
        if ($this->max_price !== null && (int) $listing->price > (int) $this->max_price) {
            return false;
        }

        $keyword = trim((string) $this->keyword);
        if ($keyword === '') {
            return true;
        }

        $haystack = mb_strtolower(implode(' ', array_filter([
            $listing->title,
            $listing->model,
            $listing->description,
            $listing->brand?->name,
        ])));

        return str_contains($haystack, mb_strtolower($keyword));
    }
}
