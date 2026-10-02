<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Listing;

class PricingEngine
{
    private const CONDITION_RATE = [
        'like_new' => 0.86,
        'excellent' => 0.74,
        'good' => 0.61,
        'fair' => 0.46,
        'for_parts' => 0.22,
    ];

    public function estimate(array $input, ?Brand $brand = null): int
    {
        $original = max(0, (int) ($input['original_price'] ?? 0));
        if ($original <= 0) {
            return 0;
        }

        $condition = $input['condition'] ?? 'good';
        $rate = self::CONDITION_RATE[$condition] ?? 0.61;
        $year = (int) ($input['year_released'] ?? now()->year);
        $age = max(0, (int) now()->year - $year);
        $agePenalty = min(0.50, $age * 0.08);
        $brandMul = $brand ? (float) $brand->price_multiplier : 1.0;

        $value = $original * $rate * (1 - $agePenalty) * $brandMul;

        return (int) (round($value / 10000) * 10000);
    }

    public function forListing(Listing $listing): int
    {
        return $this->estimate([
            'original_price' => $listing->original_price,
            'condition' => $listing->condition,
            'year_released' => $listing->year_released,
        ], $listing->brand);
    }
}
