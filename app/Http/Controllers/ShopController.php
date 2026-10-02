<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function show(Request $request, User $user)
    {
        abort_unless($user->is_seller || $user->listings()->exists(), 404);

        $sort = $request->query('sort', 'new');
        $listings = Listing::public()
            ->where('seller_id', $user->id)
            ->with(['images', 'origin', 'brand', 'seller'])
            ->when($sort === 'price_asc', fn ($q) => $q->orderBy('price'))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('price'))
            ->when(! in_array($sort, ['price_asc', 'price_desc'], true), fn ($q) => $q->latest('published_at'))
            ->paginate(12)
            ->withQueryString();

        $reviews = $user->receivedReviews()->with(['reviewer', 'listing'])->latest()->take(12)->get();
        $reviewCount = (int) $user->receivedReviews()->count();
        $rating = $user->ratingScore();
        $soldCount = (int) Listing::where('seller_id', $user->id)->where('status', 'sold')->count();
        $starCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($user->receivedReviews()->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating') as $star => $total) {
            $star = (int) $star;
            if (isset($starCounts[$star])) {
                $starCounts[$star] = (int) $total;
            }
        }
        $chatListing = Listing::public()->where('seller_id', $user->id)->latest('published_at')->first();

        return view('shops.show', compact(
            'user',
            'listings',
            'reviews',
            'reviewCount',
            'rating',
            'soldCount',
            'starCounts',
            'sort',
            'chatListing'
        ));
    }
}
