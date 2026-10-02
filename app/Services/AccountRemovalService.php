<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Review;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class AccountRemovalService
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $listingIds = Listing::where('seller_id', $user->id)->pluck('id');
            $orderIds = Order::where('user_id', $user->id)->pluck('id')
                ->merge(OrderItem::whereIn('listing_id', $listingIds)->pluck('order_id'))
                ->merge(OrderItem::where('seller_id', $user->id)->pluck('order_id'))
                ->unique()
                ->filter()
                ->values();

            if ($orderIds->isNotEmpty()) {
                PaymentTransaction::whereIn('order_id', $orderIds)->delete();
                WalletTransaction::whereIn('order_id', $orderIds)->update(['order_id' => null]);
                OrderItem::whereIn('order_id', $orderIds)->delete();
                Order::whereIn('id', $orderIds)->delete();
            }

            Conversation::query()
                ->where('buyer_id', $user->id)
                ->orWhere('seller_id', $user->id)
                ->delete();
            Review::where('reviewer_id', $user->id)->orWhere('seller_id', $user->id)->delete();
            Listing::where('seller_id', $user->id)->delete();
            $user->delete();
        });
    }
}
