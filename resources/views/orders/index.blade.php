@extends('layouts.account')
@section('title', 'Đơn hàng')
@section('content')
<h1>Đơn của tôi</h1>
@forelse ($orders as $order)
    <div class="panel" style="margin-bottom:12px">
        <div class="meta" style="justify-content:space-between">
            <strong>{{ $order->code }}</strong>
            <span class="badge">{{ $order->statusLabel() }}</span>
        </div>
        <p class="muted">{{ $order->created_at->format('d/m/Y H:i') }} · GHN: {{ $order->ghn_order_code ?: 'chưa có' }} · {{ $order->shipping_status }} · phí {{ number_format($order->ghn_total_fee) }} ₫</p>
        <p>{{ number_format($order->total_price, 0, ',', '.') }} ₫</p>
        <a class="btn btn-ghost btn-sm" href="{{ route('user.orders.show', $order) }}">Chi tiết</a>
        @if ($order->ghnTrackingUrl())
            <a class="btn btn-ghost btn-sm" href="{{ $order->ghnTrackingUrl() }}" target="_blank" rel="noopener">Theo dõi GHN</a>
        @endif
        @if ($order->canPayAgain())
            <a class="btn btn-accent btn-sm" href="{{ route('user.orders.momo.pay', $order) }}">Thanh toán lại MoMo</a>
        @endif
    </div>
@empty
    <p class="empty">Chưa có đơn.</p>
@endforelse
<div class="pager">{{ $orders->links() }}</div>
@endsection
