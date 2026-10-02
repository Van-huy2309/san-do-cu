<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Listing;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $listings = Listing::public()
            ->whereIn('id', $request->user()->favorites()->pluck('listing_id'))
            ->with(['images', 'brand', 'category', 'origin', 'seller'])
            ->latest('published_at')
            ->paginate(12);

        return view('account.favorites', compact('listings'));
    }

    public function toggle(Request $request, Listing $listing)
    {
        abort_unless($listing->isActive() || $listing->status === 'sold', 404);

        $existing = Favorite::where('user_id', $request->user()->id)
            ->where('listing_id', $listing->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Đã bỏ khỏi yêu thích.');
        }

        Favorite::create([
            'user_id' => $request->user()->id,
            'listing_id' => $listing->id,
        ]);

        return back()->with('success', 'Đã lưu vào yêu thích.');
    }
}
