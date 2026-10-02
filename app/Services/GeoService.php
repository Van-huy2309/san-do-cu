<?php

namespace App\Services;

class GeoService
{
    public const CITIES = AreaService::AREAS;

    public function forCity(?string $city): array
    {
        $city = trim((string) $city);
        foreach (self::CITIES as $name => $pair) {
            if (mb_strtolower($name) === mb_strtolower($city)) {
                return ['lat' => $pair[0], 'lng' => $pair[1]];
            }
        }

        return ['lat' => null, 'lng' => null];
    }

    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    public function boundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $latDelta = $radiusKm / 111.32;
        $lngDelta = $radiusKm / (111.32 * max(0.2, cos(deg2rad($lat))));

        return [
            'min_lat' => $lat - $latDelta,
            'max_lat' => $lat + $latDelta,
            'min_lng' => $lng - $lngDelta,
            'max_lng' => $lng + $lngDelta,
        ];
    }
}
