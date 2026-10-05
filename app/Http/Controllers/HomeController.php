<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Services\AreaService;
use App\Services\GeoService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, GeoService $geo)
    {
        $categories = Category::activeCached();
        $brands = Brand::orderBy('name')->get();
        $area = AreaService::syncFromRequest($request);
        $user = auth()->user();
        $userCity = $area ?: $user?->city;
        $geoPoint = $request->session()->get('relic.geo', [
            'lat' => $user?->lat,
            'lng' => $user?->lng,
        ]);

        $base = Listing::public()->with(['images', 'brand', 'category', 'origin', 'seller'])
            ->withExists(['marketingEnrollments as is_advertised' => fn ($query) => $query->running()]);
        $inArea = (clone $base)->inArea($area);

        $featured = (clone $inArea)
            ->whereHas('marketingEnrollments', fn ($query) => $query->running())
            ->latest('published_at')
            ->take(8)
            ->get();

        $verified = (clone $inArea)
            ->whereHas('origin', fn ($q) => $q->where('status', 'verified'))
            ->latest('published_at')
            ->take(8)
            ->get();

        $nearby = collect();
        if ($area) {
            $nearby = (clone $inArea)->latest('published_at')->take(8)->get();
        } elseif (! empty($geoPoint['lat']) && ! empty($geoPoint['lng'])) {
            $box = $geo->boundingBox((float) $geoPoint['lat'], (float) $geoPoint['lng'], 25);
            $nearby = (clone $inArea)->whereNotNull('lat')
                ->whereBetween('lat', [$box['min_lat'], $box['max_lat']])
                ->whereBetween('lng', [$box['min_lng'], $box['max_lng']])
                ->latest('published_at')
                ->take(8)
                ->get();
        } elseif ($userCity) {
            $nearby = (clone $inArea)->inArea($userCity)->latest('published_at')->take(8)->get();
        }

        $suggested = (clone $base)->latest('published_at')->take(12)->get();

        $adListings = Listing::public()
            ->with(['images', 'category', 'brand'])
            ->whereHas('marketingEnrollments', fn ($query) => $query->running())
            ->latest('published_at')
            ->take(8)
            ->get();
        $filmstripAds = ($adListings->isNotEmpty() ? $adListings : Listing::public()
            ->with(['images', 'category', 'brand'])
            ->orderByDesc('views')
            ->orderByDesc('published_at')
            ->take(8)
            ->get())
            ->map(function (Listing $listing) {
                return [
                    'id' => $listing->id,
                    'name' => $listing->title,
                    'icon' => $listing->category?->icon ?: '✨',
                    'price' => $listing->formattedPrice(),
                    'image' => $listing->coverUrl(),
                    'url' => route('listings.show', $listing),
                ];
            })
            ->values();

        return view('home', compact(
            'categories', 'brands', 'featured', 'verified', 'nearby', 'suggested', 'userCity', 'area', 'filmstripAds'
        ))->with('currentArea', $area)->with('areas', AreaService::names());
    }
}
