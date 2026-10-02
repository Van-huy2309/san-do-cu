<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Services\AreaService;
use App\Services\ListingSearch;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request, ListingSearch $search)
    {
        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $this->rememberSearch($request, $q);
        }

        if (! $request->filled('lat') && $request->session()->has('relic.geo')) {
            $request->merge($request->session()->get('relic.geo'));
        }

        AreaService::syncFromRequest($request);

        $data = $search->paginate($request);

        return view('listings.index', $data);
    }

    public function show(Request $request, Listing $listing)
    {
        abort_unless(
            in_array($listing->status, ['active', 'sold', 'reserved'], true)
            || (auth()->check() && (auth()->id() === $listing->seller_id || auth()->user()->isAdmin())),
            404
        );

        $listing->load(['images', 'brand', 'category', 'origin', 'seller', 'reviews.reviewer']);
        $listing->increment('views');

        $related = Listing::public()
            ->where('category_id', $listing->category_id)
            ->where('id', '!=', $listing->id)
            ->with(['images', 'origin', 'seller'])
            ->take(4)
            ->get();

        $this->rememberSearch($request, $listing->brand->name ?? $listing->category->name);

        $shopReviews = $listing->seller->receivedReviews()->with('reviewer')->latest()->take(20)->get();
        $allRatings = $listing->seller->receivedReviews()->pluck('rating');
        $shopCount = $allRatings->count();
        $shopAvg = $shopCount > 0 ? (float) $allRatings->avg() : 0;
        $shopListingCount = Listing::public()->where('seller_id', $listing->seller_id)->count();
        $breakdown = [];
        for ($star = 5; $star >= 1; $star--) {
            $breakdown[$star] = $allRatings->filter(fn ($r) => (int) $r === $star)->count();
        }

        $canReview = auth()->check()
            && $listing->seller_id !== auth()->id()
            && auth()->user()->orders()
                ->whereIn('status', ['paid', 'cod_ordered', 'completed'])
                ->whereHas('items', fn ($q) => $q->where('listing_id', $listing->id))
                ->exists();

        $myReview = auth()->check()
            ? $listing->reviews->firstWhere('reviewer_id', auth()->id())
            : null;

        $shopChat = null;
        $shopThreads = collect();
        if (auth()->check() && auth()->id() === $listing->seller_id) {
            $shopThreads = Conversation::with(['buyer', 'messages.user'])
                ->where('listing_id', $listing->id)
                ->orderByDesc('last_message_at')
                ->take(8)
                ->get();
        } elseif (auth()->check() && ! auth()->user()->isAdmin()) {
            $shopChat = Conversation::with(['messages.user', 'seller'])
                ->where('listing_id', $listing->id)
                ->where('buyer_id', auth()->id())
                ->first();
            if ($shopChat) {
                $shopChat->messages()
                    ->where('user_id', '!=', auth()->id())
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }
        }

        return view('listings.show', compact(
            'listing', 'related', 'shopReviews', 'shopAvg', 'shopCount', 'shopListingCount', 'breakdown', 'canReview', 'myReview',
            'shopChat', 'shopThreads'
        ));
    }

    private function rememberSearch(Request $request, string $term): void
    {
        $term = trim($term);
        if ($term === '' || mb_strlen($term) > 40 || ! $request->hasSession()) {
            return;
        }

        $terms = collect($request->session()->get('relic.searches', []))
            ->reject(fn ($t) => mb_strtolower($t) === mb_strtolower($term))
            ->prepend($term)
            ->take(8)
            ->values()
            ->all();

        $request->session()->put('relic.searches', $terms);
    }

    public function report(Request $request, Listing $listing)
    {
        $data = $request->validate([
            'reason' => 'required|in:fake,wrong_price,scam,other',
            'detail' => 'nullable|string|max:500',
        ]);

        ListingReport::create([
            'user_id' => $request->user()->id,
            'listing_id' => $listing->id,
            'reason' => $data['reason'],
            'detail' => $data['detail'] ?? null,
        ]);

        return back()->with('success', 'Đã gửi báo cáo. Relic sẽ xem xét tin này.');
    }
}
