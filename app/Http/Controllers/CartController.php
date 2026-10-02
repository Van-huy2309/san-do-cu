<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $cart = session('cart', []);
        $totalPrice = collect($cart)->sum(fn ($item) => $item['price'] * $item['quantity']);

        return view('cart.index', compact('cart', 'totalPrice'));
    }

    public function add(Request $request, Listing $listing)
    {
        abort_unless($listing->isActive(), 404);

        if (auth()->check() && $listing->seller_id === auth()->id()) {
            return back()->with('error', 'Bạn không thể mua tin của chính mình.');
        }

        $price = (int) $listing->price;
        if (auth()->check()) {
            $deal = \App\Models\Conversation::where('listing_id', $listing->id)
                ->where('buyer_id', auth()->id())
                ->whereNotNull('accepted_price')
                ->value('accepted_price');
            if ($deal) {
                $price = (int) $deal;
            }
        }

        $cart = session('cart', []);
        $key = (string) $listing->id;

        if (! isset($cart[$key])) {
            $cart[$key] = [
                'id' => $listing->id,
                'slug' => $listing->slug,
                'seller_id' => $listing->seller_id,
                'name' => $listing->title,
                'price' => $price,
                'quantity' => 1,
                'weight' => (int) $listing->weight,
                'image' => $listing->coverUrl(),
            ];
            session(['cart' => $cart]);
        }

        if ($request->boolean('buy_now')) {
            return redirect()->route('user.payment.index');
        }

        return back()->with('success', 'Đã thêm vào giỏ hàng.');
    }

    public function remove(string $key)
    {
        $cart = session('cart', []);
        unset($cart[$key]);
        session(['cart' => $cart]);

        return back()->with('success', 'Đã xóa khỏi giỏ hàng.');
    }
}
