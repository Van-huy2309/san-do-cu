<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class AreaService
{
    /** @var array<string, array{0: float, 1: float}> */
    public const AREAS = [
        'Hà Nội' => [21.0278, 105.8342],
        'Hồ Chí Minh' => [10.7769, 106.7009],
        'Đà Nẵng' => [16.0544, 108.2022],
        'Hải Phòng' => [20.8449, 106.6881],
        'Cần Thơ' => [10.0452, 105.7469],
        'Huế' => [16.4637, 107.5909],
        'An Giang' => [10.5216, 105.1259],
        'Bà Rịa - Vũng Tàu' => [10.5417, 107.2431],
        'Bắc Giang' => [21.2810, 106.1976],
        'Bắc Kạn' => [22.1470, 105.8348],
        'Bạc Liêu' => [9.2941, 105.7278],
        'Bắc Ninh' => [21.1214, 106.1110],
        'Bến Tre' => [10.2434, 106.3756],
        'Bình Định' => [13.7820, 109.2190],
        'Bình Dương' => [11.1667, 106.6667],
        'Bình Phước' => [11.7512, 106.7235],
        'Bình Thuận' => [10.9378, 108.1221],
        'Cà Mau' => [9.1768, 105.1524],
        'Cao Bằng' => [22.6666, 106.2639],
        'Đắk Lắk' => [12.6667, 108.0500],
        'Đắk Nông' => [12.2646, 107.6098],
        'Điện Biên' => [21.3860, 103.0230],
        'Đồng Nai' => [10.9574, 106.8430],
        'Đồng Tháp' => [10.4938, 105.6882],
        'Gia Lai' => [13.9833, 108.0000],
        'Hà Giang' => [22.8233, 104.9836],
        'Hà Nam' => [20.5411, 105.9229],
        'Hà Tĩnh' => [18.3333, 105.9000],
        'Hải Dương' => [20.9373, 106.3146],
        'Hậu Giang' => [9.7579, 105.6413],
        'Hòa Bình' => [20.8172, 105.3376],
        'Hưng Yên' => [20.6464, 106.0511],
        'Khánh Hòa' => [12.2388, 109.1967],
        'Kiên Giang' => [10.0125, 105.0809],
        'Kon Tum' => [14.3497, 108.0000],
        'Lai Châu' => [22.3964, 103.4586],
        'Lâm Đồng' => [11.9404, 108.4583],
        'Lạng Sơn' => [21.8537, 106.7610],
        'Lào Cai' => [22.4809, 103.9755],
        'Long An' => [10.5439, 106.4131],
        'Nam Định' => [20.4200, 106.1683],
        'Nghệ An' => [18.6796, 105.6813],
        'Ninh Bình' => [20.2506, 105.9745],
        'Ninh Thuận' => [11.5643, 108.9886],
        'Phú Thọ' => [21.3227, 105.1350],
        'Phú Yên' => [13.0882, 109.0929],
        'Quảng Bình' => [17.4689, 106.6223],
        'Quảng Nam' => [15.5394, 108.0191],
        'Quảng Ngãi' => [15.1214, 108.8044],
        'Quảng Ninh' => [21.0064, 107.2925],
        'Quảng Trị' => [16.7500, 107.2000],
        'Sóc Trăng' => [9.6025, 105.9739],
        'Sơn La' => [21.3270, 103.9141],
        'Tây Ninh' => [11.3350, 106.1098],
        'Thái Bình' => [20.4463, 106.3366],
        'Thái Nguyên' => [21.5672, 105.8252],
        'Thanh Hóa' => [19.8075, 105.7764],
        'Tiền Giang' => [10.3600, 106.3600],
        'Trà Vinh' => [9.9347, 106.3455],
        'Tuyên Quang' => [21.8233, 105.2140],
        'Vĩnh Long' => [10.2397, 105.9571],
        'Vĩnh Phúc' => [21.3089, 105.6049],
        'Yên Bái' => [21.7168, 104.8986],
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::AREAS);
    }

    public static function isValid(?string $area): bool
    {
        return $area !== null && $area !== '' && isset(self::AREAS[$area]);
    }

    public static function current(?Request $request = null): ?string
    {
        $request ??= request();
        if (! $request?->hasSession()) {
            return null;
        }
        $area = $request->session()->get('relic.area');

        return self::isValid($area) ? $area : null;
    }

    public static function remember(Request $request, ?string $area): void
    {
        if (self::isValid($area)) {
            $request->session()->put('relic.area', $area);
        } else {
            $request->session()->forget('relic.area');
        }
    }

    public static function syncFromRequest(Request $request): ?string
    {
        if ($request->exists('city')) {
            $area = $request->input('city');
            self::remember($request, is_string($area) ? $area : null);
        }

        $current = self::current($request);
        if ($current && class_exists(\App\Models\Listing::class)
            && \App\Models\Listing::public()->inArea($current)->doesntExist()) {
            self::remember($request, null);
        }

        $fromQuery = $request->query('city');

        return self::isValid(is_string($fromQuery) ? $fromQuery : null) ? $fromQuery : null;
    }

    public static function applyToQuery(Builder $query, ?string $area): Builder
    {
        if (! self::isValid($area)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($area) {
            $q->where('city', $area);
            if (\Illuminate\Support\Facades\Schema::hasColumn('listings', 'areas')) {
                $q->orWhereJsonContains('areas', $area);
            }
        });
    }
}
