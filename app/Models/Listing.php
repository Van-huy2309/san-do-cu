<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Listing extends Model
{
    public const CONDITIONS = [
        'like_new' => 'Mới 99%',
        'excellent' => 'Xuất sắc',
        'good' => 'Tốt / trầy nhẹ',
        'fair' => 'Khá',
        'for_parts' => 'Xác / linh kiện',
    ];

    protected $fillable = [
        'seller_id', 'category_id', 'brand_id', 'title', 'slug', 'description',
        'model', 'color', 'storage_gb', 'year_released', 'condition',
        'original_price', 'price', 'estimated_price', 'weight', 'city', 'areas', 'lat', 'lng', 'extras',
        'status', 'views', 'is_featured', 'featured_until', 'published_at', 'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
            'areas' => 'array',
            'extras' => 'array',
            'featured_until' => 'datetime',
            'published_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Listing $listing) {
            if (! $listing->slug) {
                $listing->slug = Str::slug($listing->title) . '-' . Str::lower(Str::random(6));
            }
        });
        static::saved(function (Listing $listing) {
            try {
                app(\App\Services\ElasticsearchService::class)->indexListing($listing);
            } catch (\Throwable) {
                // Elasticsearch optional
            }
        });
        static::deleted(function (Listing $listing) {
            try {
                app(\App\Services\ElasticsearchService::class)->deleteListing($listing->id);
            } catch (\Throwable) {
            }
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    public function origin(): HasOne
    {
        return $this->hasOne(ListingOrigin::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function marketingEnrollments(): HasMany
    {
        return $this->hasMany(MarketingEnrollment::class);
    }

    public function extra(string $key, mixed $default = null): mixed
    {
        return data_get($this->extras, $key, $default);
    }

    public function isAdvertised(): bool
    {
        if (array_key_exists('is_advertised', $this->attributes)) {
            return (bool) $this->is_advertised;
        }

        return $this->marketingEnrollments()->running()->exists();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_review' => 'Chờ duyệt',
            'hidden' => 'Đang ẩn',
            'reserved' => 'Đang giữ chỗ',
            'sold' => 'Đã bán',
            'rejected' => 'Từ chối',
            default => $this->status,
        };
    }

    public function coverUrl(): string
    {
        $path = $this->images->first()?->path;

        return app(\App\Services\MediaService::class)->url($path);
    }

    public function conditionLabel(): string
    {
        return self::CONDITIONS[$this->condition] ?? $this->condition;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOriginVerified(): bool
    {
        return $this->origin?->status === 'verified';
    }

    public function formattedPrice(): string
    {
        return number_format((int) $this->price, 0, ',', '.') . ' ₫';
    }

    public function scopePublic($query)
    {
        return $query->where('status', 'active');
    }

    /** @return list<string> */
    public function areaList(): array
    {
        $areas = collect($this->areas ?? [])
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->values()
            ->all();
        if ($this->city && ! in_array($this->city, $areas, true)) {
            array_unshift($areas, $this->city);
        }

        return array_values(array_unique($areas));
    }

    public function areasLabel(): string
    {
        $areas = $this->areaList();

        return $areas === [] ? 'Toàn quốc' : implode(', ', $areas);
    }

    public function scopeInArea($query, ?string $area)
    {
        return \App\Services\AreaService::applyToQuery($query, $area);
    }
}
