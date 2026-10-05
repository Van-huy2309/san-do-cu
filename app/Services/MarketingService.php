<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\MarketingEnrollment;
use App\Models\MarketingPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarketingService
{
    public function enroll(Listing $listing, User $seller, int $packageId): MarketingEnrollment
    {
        return DB::transaction(function () use ($listing, $seller, $packageId) {
            $package = MarketingPackage::query()->whereKey($packageId)->lockForUpdate()->first();
            if (! $package || ! $package->is_active) {
                throw new \RuntimeException('Gói marketing không còn mở.');
            }

            $already = MarketingEnrollment::query()
                ->where('marketing_package_id', $package->id)
                ->where('listing_id', $listing->id)
                ->where('ends_at', '>', now())
                ->exists();
            if ($already) {
                throw new \RuntimeException('Tin này đang nằm trong gói quảng cáo đó.');
            }

            $taken = MarketingEnrollment::query()
                ->where('marketing_package_id', $package->id)
                ->where('ends_at', '>', now())
                ->count();
            if ($taken >= (int) $package->slot_limit) {
                throw new \RuntimeException('Gói "'.$package->name.'" đã hết suất ('.$package->slot_limit.' sản phẩm).');
            }

            return MarketingEnrollment::create([
                'marketing_package_id' => $package->id,
                'listing_id' => $listing->id,
                'seller_id' => $seller->id,
                'ends_at' => now()->addDays((int) $package->duration_days),
            ]);
        });
    }
};
