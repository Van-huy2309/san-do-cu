<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\ListingSearch;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request, ListingSearch $search)
    {
        $data = $search->paginate($request);
        $items = $data['listings']->getCollection()->map(fn (Listing $listing) => $this->card($listing));

        return response()->json([
            'search' => $data['search'],
            'elastic' => $data['usedElastic'],
            'data' => $items,
            'meta' => [
                'current_page' => $data['listings']->currentPage(),
                'last_page' => $data['listings']->lastPage(),
                'total' => $data['listings']->total(),
            ],
        ]);
    }

    public function show(Listing $listing)
    {
        abort_unless(in_array($listing->status, ['active', 'sold', 'reserved'], true), 404);
        $listing->load(['images', 'brand', 'category', 'origin', 'seller']);
        $listing->increment('views');

        return response()->json($this->card($listing, true));
    }

    private function card(Listing $listing, bool $detail = false): array
    {
        $row = [
            'id' => $listing->id,
            'slug' => $listing->slug,
            'title' => $listing->title,
            'price' => (int) $listing->price,
            'formatted_price' => $listing->formattedPrice(),
            'city' => $listing->city,
            'areas' => $listing->areaList(),
            'lat' => $listing->lat,
            'lng' => $listing->lng,
            'distance_km' => $listing->getAttribute('distance_km'),
            'condition' => $listing->conditionLabel(),
            'brand' => $listing->brand->name ?? null,
            'category' => $listing->category->name ?? null,
            'cover' => $listing->coverUrl(),
            'verified' => $listing->isOriginVerified(),
        ];
        if ($detail) {
            $row['description'] = $listing->description;
            $row['images'] = $listing->images->map(fn ($img) => app(\App\Services\MediaService::class)->url($img->path))->all();
            $row['seller'] = [
                'id' => $listing->seller_id,
                'name' => $listing->seller->name,
            ];
        }

        return $row;
    }
}
