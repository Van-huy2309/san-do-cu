<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Services\EscrowService;
use App\Services\GHNOrderService;
use App\Services\GHNService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function checkout()
    {
        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('user.cart.index')->with('error', 'Giỏ hàng đang trống.');
        }

        $totalPrice = collect($cart)->sum(fn ($item) => $item['price'] * $item['quantity']);

        return view('orders.checkout', compact('cart', 'totalPrice'));
    }

    public function history()
    {
        $orders = Order::where('user_id', Auth::id())
            ->with(['items.listing', 'paymentTransactions' => fn ($q) => $q->latest()])
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        if ($order->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403);
        }

        $order->load(['items.listing.images', 'paymentTransactions' => fn ($q) => $q->latest()]);

        return view('orders.show', compact('order'));
    }

    public function cancel(Order $order, GHNService $ghn, EscrowService $escrow)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        if (in_array($order->status, ['completed', 'refunded', 'cancelled'], true) || $order->escrow_status === 'released') {
            return back()->with('error', 'Đơn đã hoàn tất hoặc đã giải ngân, không hủy được.');
        }

        $allowedStatuses = ['pending', 'ready_to_pick', 'not_shipped'];
        if (! in_array($order->shipping_status, $allowedStatuses, true)) {
            return back()->with('error', 'Đơn hàng không còn ở trạng thái có thể hủy.');
        }

        if ($order->ghn_order_code) {
            $response = $ghn->cancelOrder([$order->ghn_order_code]);
            if (($response['code'] ?? null) !== 200) {
                return back()->with('error', 'GHN không cho phép hủy vận đơn này.');
            }
        }

        DB::transaction(function () use ($order, $escrow) {
            $order->load('items.listing', 'user');
            $refundEscrow = in_array($order->escrow_status, ['held', 'disputed'], true);
            if ($refundEscrow) {
                $escrow->refundBuyer($order, 'Người mua hủy đơn, hoàn tiền escrow');
                $order->refresh();
            }

            $order->update([
                'status' => $refundEscrow ? 'refunded' : 'cancelled',
                'shipping_status' => 'cancelled',
            ]);
            $order->paymentTransactions()
                ->whereIn('status', ['pending', 'initiated'])
                ->update(['status' => 'cancelled']);
            $order->paymentTransactions()
                ->where('status', 'paid')
                ->update(['status' => 'refund_pending']);

            foreach ($order->items as $item) {
                if ($item->listing && in_array($item->listing->status, ['reserved', 'sold'], true)) {
                    $item->listing->update(['status' => 'active', 'sold_at' => null]);
                }
            }
        });

        return back()->with('success', $order->fresh()->status === 'refunded'
            ? 'Đơn đã hủy và tiền escrow được hoàn vào ví.'
            : 'Đơn hàng đã được hủy.');
    }

    public function process(Request $request, GHNService $ghn, GHNOrderService $ghnOrders)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'phone' => ['required', 'regex:/^0\d{9}$/'],
            'address' => 'required|string|max:255',
            'to_district_id' => 'required|integer',
            'to_ward_code' => 'required|string',
            'payment_method' => 'required|in:cod,momo',
        ]);

        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('user.cart.index')->with('error', 'Không thể thanh toán vì giỏ hàng trống.');
        }

        $subtotal = collect($cart)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $totalWeight = collect($cart)->sum(
            fn ($item) => (int) ($item['weight'] ?? $ghn->productWeight()) * (int) $item['quantity']
        );

        $feeResponse = $ghn->calculateFee(array_merge([
            'to_district_id' => (int) $request->to_district_id,
            'to_ward_code' => (string) $request->to_ward_code,
        ], $ghn->packageParameters($totalWeight)));

        $shippingFee = (isset($feeResponse['code']) && (int) $feeResponse['code'] === 200 && isset($feeResponse['data']['total']))
            ? (int) $feeResponse['data']['total']
            : 0;

        $finalTotal = $subtotal + $shippingFee;

        try {
            $order = DB::transaction(function () use ($request, $shippingFee, $finalTotal, $cart) {
                $fresh = Listing::whereIn('id', collect($cart)->pluck('id'))->lockForUpdate()->get()->keyBy('id');

                foreach ($cart as $item) {
                    $listing = $fresh->get($item['id']);
                    if (! $listing || ! $listing->isActive()) {
                        throw new \RuntimeException('Tin "' . ($item['name'] ?? '') . '" vừa được bán hoặc ẩn. Hãy xóa khỏi giỏ.');
                    }
                    if ((int) ($item['quantity'] ?? 1) !== 1) {
                        throw new \RuntimeException('Mỗi tin chỉ bán một máy. Hãy để số lượng là 1.');
                    }
                    if ((int) $listing->price !== (int) $item['price']) {
                        $deal = \App\Models\Conversation::where('listing_id', $listing->id)
                            ->where('buyer_id', Auth::id())
                            ->value('accepted_price');
                        if ((int) $deal !== (int) $item['price']) {
                            throw new \RuntimeException('Giá "' . $listing->title . '" đã đổi. Vui lòng làm mới giỏ hàng.');
                        }
                    }
                }

                $order = Order::create([
                    'code' => 'RLC' . now()->format('ymd') . strtoupper(Str::random(5)),
                    'user_id' => Auth::id(),
                    'name' => $request->name,
                    'address' => $request->address,
                    'phone' => $request->phone,
                    'total_price' => $finalTotal,
                    'status' => 'pending',
                    'to_district_id' => (int) $request->to_district_id,
                    'to_ward_code' => (string) $request->to_ward_code,
                    'ghn_total_fee' => $shippingFee,
                    'shipping_status' => 'pending',
                ]);

                foreach ($cart as $item) {
                    $listing = $fresh->get($item['id']);
                    OrderItem::create([
                        'order_id' => $order->id,
                        'listing_id' => $listing->id,
                        'seller_id' => $listing->seller_id,
                        'title' => $listing->title,
                        'quantity' => 1,
                        'price' => (int) $item['price'],
                    ]);
                }

                return $order;
            });
        } catch (\RuntimeException $e) {
            return redirect()->route('user.cart.index')->with('error', $e->getMessage());
        }

        session()->forget('cart');

        if ($request->payment_method === 'momo') {
            PaymentTransaction::create([
                'order_id' => $order->id,
                'gateway' => 'momo',
                'amount' => $order->total_price,
                'status' => 'pending',
            ]);

            return redirect()->route('user.orders.momo.start', $order);
        }

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway' => 'cod',
            'amount' => $order->total_price,
            'status' => 'pending',
            'message' => 'Thanh toán khi nhận hàng',
        ]);

        $order->load('items.listing');
        $ghnOrderResponse = $ghnOrders->create($order);

        if (($ghnOrderResponse['code'] ?? null) == 200 && ! empty($ghnOrderResponse['data']['order_code'])) {
            $order->update([
                'status' => 'cod_ordered',
                'ghn_order_code' => $ghnOrderResponse['data']['order_code'],
                'shipping_status' => 'picking',
            ]);
            app(EscrowService::class)->markCod($order->fresh('items'));

            return redirect()->route('user.orders.index')
                ->with('success', 'Đặt hàng thành công! Mã vận đơn GHN: ' . $ghnOrderResponse['data']['order_code']);
        }

        Log::error('GHN COD Order Failed: ', $ghnOrderResponse ?? []);
        $order->update([
            'status' => 'cod_ordered',
            'shipping_status' => 'pending',
        ]);
        app(EscrowService::class)->markCod($order->fresh('items'));

        return redirect()->route('user.orders.index')
            ->with('warning', 'Đặt hàng thành công nhưng chưa thể tạo vận đơn GHN tự động.');
    }

    public function confirmReceived(Order $order, EscrowService $escrow)
    {
        abort_unless($order->user_id === Auth::id(), 403);
        abort_unless($order->canConfirmReceived(), 403);

        $wasCod = $order->escrow_status === 'cod';
        $escrow->releaseToSellers($order->load('items'));

        $message = $wasCod
            ? 'Đã xác nhận nhận hàng (COD, không qua ví escrow).'
            : 'Đã xác nhận nhận hàng. Tiền escrow được giải ngân vào ví người bán (trừ 5% phí sàn).';

        return back()->with('success', $message);
    }
}
