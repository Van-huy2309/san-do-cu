<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ListingSearch
{
    public function __construct(
        private ElasticsearchService $elasticsearch,
        private GeoService $geo,
    ) {}

    public function paginate(Request $request): array
    {
        $search = trim((string) $request->get('q', ''));
        $sort = $request->get('sort', 'newest');
        $tokens = $search === ''
            ? collect()
            : collect(preg_split('/\s+/u', $search))->filter()->take(6)->values();

        $query = $this->filteredQuery($request);
        $usedElastic = false;

        if ($search !== '' && $this->elasticsearch->enabled()) {
            try {
                $ids = $this->elasticsearch->searchIds($search, [
                    'verified' => $request->boolean('verified'),
                    'lat' => $request->get('lat'),
                    'lng' => $request->get('lng'),
                    'radius' => $request->get('radius', 25),
                ]);
                $usedElastic = true;
                $query->when(
                    $ids === [],
                    fn ($q) => $q->whereRaw('1=0'),
                    fn ($q) => $q->whereIn('id', $ids)
                );
            } catch (\Throwable $e) {
                Log::info('Elasticsearch fallback to SQL', ['error' => $e->getMessage()]);
                $query = $this->applySqlSearch($this->filteredQuery($request), $tokens, true);
                if ($tokens->count() > 1 && (clone $query)->doesntExist()) {
                    $query = $this->applySqlSearch($this->filteredQuery($request), $tokens, false);
                }
            }
        } elseif ($tokens->isNotEmpty()) {
            $query = $this->applySqlSearch($query, $tokens, true);
            if ($tokens->count() > 1 && (clone $query)->doesntExist()) {
                $query = $this->applySqlSearch($this->filteredQuery($request), $tokens, false);
            }
        }

        $lat = $request->filled('lat') ? (float) $request->get('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->get('lng') : null;
        $radius = max(1, (float) $request->get('radius', 25));

        if ($lat && $lng && ($sort === 'nearby' || $request->filled('radius') && $request->boolean('gps'))) {
            $box = $this->geo->boundingBox($lat, $lng, $radius);
            $query->whereNotNull('lat')->whereNotNull('lng')
                ->whereBetween('lat', [$box['min_lat'], $box['max_lat']])
                ->whereBetween('lng', [$box['min_lng'], $box['max_lng']]);
        }

        $this->applySort($query, $sort, $search, $lat, $lng);

        $listings = $query->paginate(12)->withQueryString();
        if ($lat && $lng) {
            $listings->getCollection()->transform(function (Listing $listing) use ($lat, $lng) {
                if ($listing->lat && $listing->lng) {
                    $listing->setAttribute(
                        'distance_km',
                        $this->geo->distanceKm($lat, $lng, (float) $listing->lat, (float) $listing->lng)
                    );
                }

                return $listing;
            });
        }

        return [
            'listings' => $listings,
            'shops' => $this->matchingShops($search),
            'categories' => Category::activeCached(),
            'brands' => Brand::orderBy('name')->get(),
            'cities' => collect(AreaService::names()),
            'currentArea' => ($city = $request->query('city')) && AreaService::isValid(is_string($city) ? $city : null) ? $city : null,
            'areas' => AreaService::names(),
            'sort' => $sort,
            'search' => $search,
            'usedElastic' => $usedElastic,
        ];
    }

    private function filteredQuery(Request $request)
    {
        $query = Listing::public()->with(['images', 'brand', 'category', 'origin', 'seller'])
            ->withExists(['marketingEnrollments as is_advertised' => fn ($query) => $query->running()]);

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }
        if ($request->filled('brand')) {
            $query->where('brand_id', $request->integer('brand'));
        }
        if ($request->filled('condition') && isset(Listing::CONDITIONS[$request->get('condition')])) {
            $query->where('condition', $request->get('condition'));
        }
        if ($request->filled('city')) {
            AreaService::applyToQuery($query, $request->get('city'));
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->integer('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->integer('max_price'));
        }
        if ($request->boolean('verified')) {
            $query->whereHas('origin', fn ($q) => $q->where('status', 'verified'));
        }

        return $query;
    }

    private function applySqlSearch($query, $tokens, bool $matchAll)
    {
        if ($tokens->isEmpty()) {
            return $query;
        }

        return $query->where(function ($outer) use ($tokens, $matchAll) {
            foreach ($tokens as $token) {
                $like = '%' . $token . '%';
                $group = function ($q) use ($like) {
                    $q->where('title', 'like', $like)
                        ->orWhere('model', 'like', $like)
                        ->orWhere('color', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like))
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                        ->orWhereHas('seller', fn ($s) => $s->where('name', 'like', $like));
                };
                $matchAll ? $outer->where($group) : $outer->orWhere($group);
            }
        });
    }

    private function applySort($query, string $sort, string $search, ?float $lat, ?float $lng): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'popular' => $query->orderByDesc('views'),
            'nearby' => $this->orderNearby($query, $lat, $lng),
            default => $search !== ''
                ? $query->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', ['%' . $search . '%'])
                    ->orderByDesc('views')
                : $query->latest('published_at'),
        };
    }

    private function orderNearby($query, ?float $lat, ?float $lng): void
    {
        if ($lat && $lng && Schema::getConnection()->getDriverName() !== 'sqlite') {
            $query->orderByRaw(
                '(6371 * acos(least(1, cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))))',
                [$lat, $lng, $lat]
            );

            return;
        }

        $city = auth()->user()?->city ?? '';
        $query->orderByRaw('CASE WHEN city = ? THEN 0 ELSE 1 END', [$city])->latest('published_at');
    }

    private function matchingShops(string $search)
    {
        if ($search === '') {
            return collect();
        }

        return User::where('is_seller', true)
            ->where('is_banned', false)
            ->where('name', 'like', '%' . $search . '%')
            ->withCount(['listings' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('listings_count')
            ->take(4)
            ->get();
    }
}
