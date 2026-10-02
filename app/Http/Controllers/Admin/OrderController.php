<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\EscrowService;
use App\Services\GHNService;
use App\Services\OrderShippingStatus;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const TABS = [
        'all' => ['label' => 'Tất cả', 'statuses' => []],
        'pending' => ['label' => 'Chờ xử lý', 'statuses' => ['pending', 'not_shipped', 'processing']],
        'ready' => ['label' => 'Chờ lấy hàng', 'statuses' => ['ready_to_pick']],
        'picking' => ['label' => 'Đang lấy hàng', 'statuses' => ['picking']],
        'delivering' => ['label' => 'Đang giao', 'statuses' => ['delivering', 'picked', 'storing', 'transporting', 'sorting']],
        'delivered' => ['label' => 'Thành công', 'statuses' => ['delivered']],
        'return' => ['label' => 'Hoàn hàng', 'statuses' => ['return', 'returning', 'returned', 'return_transporting', 'return_sorting']],
        'cancelled' => ['label' => 'Đã hủy', 'statuses' => ['cancelled']],
    ];

    public function index(Request $request): View
    {
        $paymentLabels = [
            'pending' => 'Chờ thanh toán',
            'initiated' => 'Đang chờ MoMo',
            'paid' => 'Đã thanh toán',
            'failed' => 'Thanh toán thất bại',
            'cancelled' => 'Đã hủy',
            'refund_pending' => 'Chờ hoàn tiền',
            'refunded' => 'Đã hoàn tiền',
        ];

        $shippingLabels = OrderShippingStatus::labels();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'paid_momo', 'cod_ordered', 'cod_paid', 'cancelled', 'completed', 'refunded'])],
            'payment_status' => ['nullable', Rule::in(array_keys($paymentLabels))],
            'shipping_status' => ['nullable', Rule::in(array_keys($shippingLabels))],
            'gateway' => ['nullable', Rule::in(['cod', 'momo', 'unknown'])],
            'tab' => ['nullable', Rule::in(array_keys(self::TABS))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'amount_desc', 'amount_asc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'date_to.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.',
            '*.date_format' => 'Ngày lọc không hợp lệ.',
            '*.in' => 'Giá trị bộ lọc không hợp lệ.',
        ]);

        $paymentId = DB::table('payment_transactions')->select('id')
            ->whereColumn('order_id', 'orders.id')
            ->orderByRaw("CASE WHEN status IN ('paid', 'refund_pending', 'refunded') THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(1);

        $source = DB::table('orders')
            ->leftJoin('payment_transactions as payment', function ($join) use ($paymentId) {
                $join->on('payment.order_id', '=', 'orders.id')
                    ->where('payment.id', '=', $paymentId);
            })
            ->select('orders.*')
            ->selectRaw("COALESCE(payment.gateway, CASE WHEN orders.status IN ('cod_ordered', 'cod_paid') THEN 'cod' WHEN orders.status IN ('paid', 'paid_momo') THEN 'momo' ELSE 'unknown' END) as gateway")
            ->selectRaw("COALESCE(payment.status, CASE WHEN orders.status = 'cod_ordered' THEN 'pending' WHEN orders.status IN ('cod_paid', 'paid_momo') THEN 'paid' ELSE orders.status END) as payment_status");

        $query = Order::query()->fromSub($source, 'orders');

        foreach (['status', 'payment_status', 'gateway'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $filters[$field]);
            }
        }

        if ($request->filled('search')) {
            $search = trim($filters['search']);
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('ghn_order_code', 'like', '%'.$search.'%')
                    ->orWhereExists(function ($items) use ($search) {
                        $items->select(DB::raw(1))
                            ->from('order_items')
                            ->whereColumn('order_items.order_id', 'orders.id')
                            ->where('order_items.title', 'like', '%'.$search.'%');
                    });

                if (preg_match('/^(?:#|DH|RLC)?0*(\d+)$/i', $search, $matches) && ! str_starts_with(strtoupper($search), 'RLC')) {
                    $query->orWhere('orders.id', $matches[1]);
                }
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<', Carbon::parse($filters['date_to'])->addDay()->startOfDay());
        }

        $shippingCounts = (clone $query)->select('shipping_status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('shipping_status')
            ->pluck('total', 'shipping_status');

        $tabs = collect(self::TABS)->map(function ($tab, $key) use ($shippingCounts) {
            $tab['count'] = $key === 'all'
                ? $shippingCounts->sum()
                : collect($tab['statuses'])->sum(fn ($status) => $shippingCounts->get($status, 0));

            return $tab;
        });

        $activeTab = $filters['tab'] ?? 'all';
        if ($activeTab === 'pending') {
            $query->where(function ($q) {
                $q->whereIn('shipping_status', self::TABS['pending']['statuses'])
                    ->orWhere('payment_status', 'failed');
            });
        } elseif ($activeTab !== 'all') {
            $query->whereIn('shipping_status', self::TABS[$activeTab]['statuses']);
        }

        if ($request->filled('shipping_status')) {
            $query->where('shipping_status', $filters['shipping_status']);
        }

        [$column, $direction] = match ($filters['sort'] ?? 'newest') {
            'oldest' => ['created_at', 'asc'],
            'amount_desc' => ['total_price', 'desc'],
            'amount_asc' => ['total_price', 'asc'],
            default => ['created_at', 'desc'],
        };

        $orders = $query->with('items')
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        return view('admin.orders.index', compact(
            'orders',
            'filters',
            'tabs',
            'activeTab',
            'paymentLabels',
            'shippingLabels'
        ));
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items.listing', 'paymentTransactions' => function ($query) {
            $query->latest();
        }]);

        $shippingLabels = OrderShippingStatus::labels();
        $canCancel = OrderShippingStatus::canCancel($order->shipping_status) && $order->status !== 'cancelled';
        $canDelete = OrderShippingStatus::canDelete($order->shipping_status);
        $canUpdateStatus = $canCancel;

        return view('admin.orders.show', compact('order', 'shippingLabels', 'canCancel', 'canUpdateStatus', 'canDelete'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        if (! OrderShippingStatus::canCancel($order->shipping_status)
            || $order->status === 'cancelled'
            || $order->shipping_status === 'cancelled') {
            return back()->with('error', 'Không thể đổi trạng thái đơn đang giao / đã hủy / đã giao.');
        }

        $data = $request->validate([
            'shipping_status' => ['required', Rule::in(OrderShippingStatus::DELETABLE)],
            'status' => ['nullable', Rule::in(['pending', 'cod_ordered', 'paid', 'paid_momo', 'cod_paid'])],
        ]);

        $order->update([
            'shipping_status' => $data['shipping_status'],
            'status' => $data['status'] ?? $order->status,
        ]);

        return back()->with('success', 'Đã cập nhật trạng thái đơn hàng.');
    }

    public function cancel(Order $order, GHNService $ghn, EscrowService $escrow): RedirectResponse
    {
        if (! OrderShippingStatus::canCancel($order->shipping_status) || $order->status === 'cancelled') {
            return back()->with('error', 'Đơn đang giao / đã giao / đã hủy — không được hủy.');
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
                $escrow->refundBuyer($order, 'Admin hủy đơn, hoàn tiền escrow');
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

            $this->restoreListings($order);
        });

        return back()->with('success', 'Đã hủy đơn hàng '.$order->code.'.');
    }

    public function destroy(Order $order, GHNService $ghn): RedirectResponse
    {
        if (! OrderShippingStatus::canDelete($order->shipping_status)) {
            return back()->with('error', 'Chỉ xóa được đơn ở Chờ xử lý / Chờ lấy hàng / Đang lấy hàng.');
        }

        if (in_array($order->escrow_status, ['held', 'disputed', 'released'], true)) {
            return back()->with('error', 'Đơn còn tiền escrow hoặc đã giải ngân. Hãy hủy đơn để hoàn tiền, không xóa thẳng.');
        }

        if ($order->ghn_order_code) {
            $ghn->cancelOrder([$order->ghn_order_code]);
        }

        $code = $order->code;
        DB::transaction(function () use ($order) {
            $order->load('items.listing');
            $this->restoreListings($order);
            $order->items()->delete();
            $order->paymentTransactions()->delete();
            $order->delete();
        });

        return redirect()->route('admin.orders')
            ->with('success', 'Đã xóa đơn hàng '.$code.'.');
    }

    private function restoreListings(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->listing && in_array($item->listing->status, ['reserved', 'sold'], true)) {
                $item->listing->update(['status' => 'active', 'sold_at' => null]);
            }
        }
    }
}
