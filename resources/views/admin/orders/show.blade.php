@extends('layouts.admin')
@section('title', 'Đơn '.$order->code)
@section('content')
<p class="muted"><a href="{{ route('admin.orders') }}">Đơn hàng</a> / {{ $order->code }}</p>
<h1>Đơn {{ $order->code }}</h1>

<div class="form-grid" style="align-items:start; margin-top:16px">
    <div class="panel">
        <h3>Giao hàng</h3>
        <p><strong>Khách:</strong> {{ $order->user->name ?? '—' }} · {{ $order->user->email ?? '—' }}</p>
        <p><strong>Người nhận:</strong> {{ $order->name }}</p>
        <p><strong>SĐT:</strong> {{ $order->phone }}</p>
        <p><strong>Địa chỉ:</strong> {{ $order->address }}</p>
        <p><strong>Trạng thái đơn:</strong> {{ $order->statusLabel() }}</p>
        <p><strong>Escrow:</strong> {{ $order->escrowLabel() }}</p>
        <p><strong>Vận chuyển:</strong> {{ $shippingLabels[$order->shipping_status] ?? $order->shipping_status }}</p>
        @if ($order->ghn_order_code)
            <p><strong>Mã GHN:</strong> {{ $order->ghn_order_code }}
                @if ($order->ghnTrackingUrl())
                    · <a href="{{ $order->ghnTrackingUrl() }}" target="_blank" rel="noopener">Theo dõi</a>
                @endif
            </p>
        @endif
        <p><strong>Phí ship:</strong> {{ number_format($order->ghn_total_fee ?? 0, 0, ',', '.') }} ₫</p>
        <p><strong>Tổng:</strong> {{ number_format($order->total_price, 0, ',', '.') }} ₫</p>
        <p class="muted">Đặt lúc {{ $order->created_at?->format('d/m/Y H:i') }}</p>
    </div>
    <div class="panel">
        <h3>Xử lý đơn</h3>
        @if ($canUpdateStatus)
            <form action="{{ route('admin.orders.updateStatus', $order) }}" method="POST">
                @csrf
                @method('PATCH')
                <label>Trạng thái vận chuyển</label>
                <select class="field" name="shipping_status" required>
                    @foreach (['pending','not_shipped','processing','ready_to_pick','picking'] as $st)
                        <option value="{{ $st }}" @selected($order->shipping_status === $st)>{{ $shippingLabels[$st] ?? $st }}</option>
                    @endforeach
                </select>
                <button class="btn btn-accent btn-sm" type="submit">Cập nhật</button>
            </form>
        @else
            <p class="muted">Đơn đang giao, đã giao hoặc đã hủy — không đổi trạng thái thủ công.</p>
        @endif

        @if ($canCancel)
            <form action="{{ route('admin.orders.cancel', $order) }}" method="POST" onsubmit="return confirm('Hủy đơn {{ $order->code }}?')">
                @csrf
                <button class="btn btn-sm" type="submit">Hủy đơn</button>
            </form>
        @endif

        @if ($canDelete)
            <form action="{{ route('admin.orders.destroy', $order) }}" method="POST" onsubmit="return confirm('Xóa vĩnh viễn đơn {{ $order->code }}?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-ghost btn-sm" type="submit">Xóa đơn</button>
            </form>
            <p class="muted">Chỉ xóa được khi đơn còn ở chờ xử lý, chờ lấy hàng hoặc đang lấy hàng, và chưa giữ escrow.</p>
        @endif
    </div>
</div>

<h2>Tin trong đơn</h2>
<table class="table">
    <tr><th>Tin</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr>
    @foreach ($order->items as $item)
        <tr>
            <td>{{ $item->title ?: ($item->listing->title ?? 'Tin đã xóa') }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ number_format($item->price, 0, ',', '.') }} ₫</td>
            <td>{{ number_format($item->price * $item->quantity, 0, ',', '.') }} ₫</td>
        </tr>
    @endforeach
</table>

<h2>Giao dịch thanh toán</h2>
<table class="table">
    <tr><th>Cổng</th><th>Số tiền</th><th>Trạng thái</th><th>Thời gian</th></tr>
    @forelse ($order->paymentTransactions as $tx)
        <tr>
            <td>{{ strtoupper($tx->gateway) }}</td>
            <td>{{ number_format($tx->amount, 0, ',', '.') }} ₫</td>
            <td>{{ $tx->status }}</td>
            <td>{{ $tx->paid_at?->format('d/m/Y H:i') ?? $tx->created_at?->format('d/m/Y H:i') }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="muted">Chưa có giao dịch.</td></tr>
    @endforelse
</table>
@endsection
