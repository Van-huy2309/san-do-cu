@extends('layouts.admin')
@section('title', 'Đơn hàng')
@section('content')
<h1>Đơn hàng</h1>
<p class="muted">Lọc theo vận chuyển, thanh toán MoMo/COD và mã vận đơn GHN.</p>

<p style="display:flex; flex-wrap:wrap; gap:8px">
    @foreach ($tabs as $key => $tab)
        <a class="btn btn-sm {{ $activeTab === $key ? 'btn-accent' : 'btn-ghost' }}"
           href="{{ route('admin.orders', array_merge(request()->except('page'), ['tab' => $key])) }}">
            {{ $tab['label'] }} ({{ $tab['count'] }})
        </a>
    @endforeach
</p>

<form method="GET" action="{{ route('admin.orders') }}" class="panel" style="margin:16px 0">
    <input type="hidden" name="tab" value="{{ $activeTab }}">
    <div class="form-grid">
        <div>
            <label>Tìm kiếm</label>
            <input class="field" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Mã đơn, SĐT, tên, GHN, tin">
        </div>
        <div>
            <label>Trạng thái đơn</label>
            <select class="field" name="status">
                <option value="">Tất cả</option>
                @foreach (['pending' => 'Chờ thanh toán', 'paid' => 'Đã thanh toán MoMo', 'cod_ordered' => 'COD đã ghi nhận', 'cod_paid' => 'COD đã thu', 'completed' => 'Hoàn tất', 'refunded' => 'Đã hoàn', 'cancelled' => 'Đã hủy'] as $st => $label)
                    <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Thanh toán</label>
            <select class="field" name="payment_status">
                <option value="">Tất cả</option>
                @foreach ($paymentLabels as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['payment_status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Vận chuyển</label>
            <select class="field" name="shipping_status">
                <option value="">Tất cả</option>
                @foreach ($shippingLabels as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['shipping_status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Cổng thanh toán</label>
            <select class="field" name="gateway">
                <option value="">Tất cả</option>
                @foreach (['cod' => 'COD', 'momo' => 'MoMo', 'unknown' => 'Không rõ'] as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['gateway'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Từ ngày</label>
            <input class="field" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div>
            <label>Đến ngày</label>
            <input class="field" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div>
            <label>Sắp xếp</label>
            <select class="field" name="sort">
                @foreach (['newest' => 'Mới nhất', 'oldest' => 'Cũ nhất', 'amount_desc' => 'Giá cao → thấp', 'amount_asc' => 'Giá thấp → cao'] as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['sort'] ?? 'newest') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <button class="btn btn-accent" type="submit">Lọc</button>
    <a class="btn btn-ghost" href="{{ route('admin.orders') }}">Xóa lọc</a>
</form>

<table class="table">
    <tr>
        <th>Mã</th>
        <th>Khách</th>
        <th>Tin</th>
        <th>Tổng</th>
        <th>Thanh toán / ship</th>
        <th>Ngày</th>
        <th></th>
    </tr>
    @forelse ($orders as $order)
        <tr>
            <td>
                <a href="{{ route('admin.orders.show', $order) }}"><strong>{{ $order->code }}</strong></a>
                @if ($order->ghn_order_code)
                    <div class="muted">GHN {{ $order->ghn_order_code }}</div>
                @endif
            </td>
            <td>
                {{ $order->name }}
                <div class="muted">{{ $order->phone }}</div>
            </td>
            <td>
                @foreach ($order->items->take(2) as $item)
                    <div>{{ $item->title }} ×{{ $item->quantity }}</div>
                @endforeach
                @if ($order->items->count() > 2)
                    <div class="muted">+{{ $order->items->count() - 2 }} tin khác</div>
                @endif
            </td>
            <td>{{ number_format($order->total_price, 0, ',', '.') }} ₫</td>
            <td>
                <div>{{ $order->statusLabel() }}</div>
                <div class="muted">{{ $shippingLabels[$order->shipping_status] ?? $order->shipping_status }}</div>
                <div class="muted">{{ strtoupper($order->gateway ?? '—') }} · {{ $paymentLabels[$order->payment_status] ?? ($order->payment_status ?? '—') }}</div>
            </td>
            <td>{{ $order->created_at?->format('d/m/Y H:i') }}</td>
            <td><a class="btn btn-sm btn-ghost" href="{{ route('admin.orders.show', $order) }}">Xem</a></td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">Không có đơn hàng.</td></tr>
    @endforelse
</table>
{{ $orders->links() }}
@endsection
