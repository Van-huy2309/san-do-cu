<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerReviewController extends Controller
{
    public function index()
    {
        abort_if(Auth::user()->isAdmin(), 403);

        $reviews = Review::query()
            ->where('seller_id', Auth::id())
            ->with(['reviewer:id,name', 'listing:id,title,slug'])
            ->latest()
            ->paginate(20);

        return view('seller.reviews.index', compact('reviews'));
    }

    public function reply(Request $request, Review $review)
    {
        abort_if($request->user()->isAdmin(), 403);
        abort_unless((int) $review->seller_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'seller_reply' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        $review->update([
            'seller_reply' => $data['seller_reply'],
            'replied_at' => now(),
        ]);

        return back()->with('success', 'Đã gửi phản hồi. Người mua thấy câu trả lời trên trang sản phẩm.');
    }
}
