@extends('layouts.account')
@section('title', 'Đơn '.$order->code)
@section('content')
<h1>Đơn {{ $order->code }}</h1>
<div class="panel">
    <p>Trạng thái: <strong>{{ $order->statusLabel() }}</strong> · Escrow: <strong>{{ $order->escrowLabel() }}</strong></p>
    <p>Vận chuyển: {{ $order->shipping_status }}</p>
    <p>Người nhận: {{ $order->name }} · {{ $order->phone }}</p>
    <p>{{ $order->address }}</p>
    <p>Mã GHN: {{ $order->ghn_order_code ?: '—' }} · Phí Relic đã tính: {{ number_format($order->ghn_total_fee) }} ₫</p>
        <p class="muted">GHN sandbox shop 219639. MoMo lab 06: thẻ ATM 9704 0000 0000 0018 / 12/30 / OTP. Đơn được chốt khi redirect về Relic; IPN không cần về localhost. Thanh toán lại không tạo đơn mới.</p>
    <p>
        <a href="{{ $order->ghnPortalUrl() }}" target="_blank" rel="noopener">Mở shop GHN</a>
        ·
        <a href="https://ghn.vn" target="_blank" rel="noopener">Trang GHN</a>
        ·
        <a href="https://khachhang.ghn.vn" target="_blank" rel="noopener">Tính cước GHN (thật)</a>
        ·
        <a href="{{ route('user.payment.index') }}">Tính lại phí trên Relic</a>
        @if ($order->ghnTrackingUrl())
            · <a href="{{ $order->ghnTrackingUrl() }}" target="_blank" rel="noopener">Theo dõi đơn {{ $order->ghn_order_code }}</a>
        @endif
    </p>
    @foreach ($order->items as $item)
        <div class="meta" style="justify-content:space-between">
            <span>{{ $item->title }}</span>
            <strong>{{ number_format($item->price, 0, ',', '.') }} ₫</strong>
        </div>
        @if ($order->user_id === auth()->id() && $item->listing && in_array($order->status, ['paid', 'cod_ordered', 'completed'], true))
            <form method="post" action="{{ route('reviews.store', $item->listing) }}" style="margin:8px 0">
                @csrf
                <select class="field" name="rating">
                    @for ($i=5;$i>=1;$i--) <option value="{{ $i }}">{{ $i }} sao</option> @endfor
                </select>
                <input class="field" name="comment" placeholder="Nhận xét máy / người bán">
                <button class="btn btn-sm">Gửi đánh giá</button>
            </form>
        @endif
    @endforeach
    <p>Tổng: <strong>{{ number_format($order->total_price, 0, ',', '.') }} ₫</strong></p>
    @if (in_array($order->shipping_status, ['pending','ready_to_pick','not_shipped'], true) && $order->status !== 'cancelled')
        <form method="post" action="{{ route('user.orders.cancel', $order) }}" onsubmit="return confirm('Hủy đơn?')">@csrf<button class="btn btn-danger">Hủy đơn</button></form>
    @endif
    @if ($order->canConfirmReceived())
        <form method="post" action="{{ route('user.orders.receive', $order) }}" onsubmit="return confirm('Xác nhận đã nhận đúng mô tả? Tiền sẽ chuyển ví người bán.')" style="margin-top:10px">
            @csrf<button class="btn btn-accent">Đã nhận hàng đúng mô tả</button>
        </form>
    @endif
    @if ($order->canDispute())
        <form class="panel" method="post" action="{{ route('user.orders.dispute', $order) }}" enctype="multipart/form-data" style="margin-top:16px">
            @csrf
            <h3>Trả hàng / hoàn tiền</h3>
            <select class="field" name="reason">
                @foreach (\App\Models\Dispute::REASONS as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                @endforeach
            </select>
            <textarea class="field" name="detail" rows="3" placeholder="Mô tả sai khác + hoàn cảnh unbox" required></textarea>
            <label>Bằng chứng (ảnh / video / PDF)</label>
            <input class="field" type="file" name="evidence" accept="image/*,video/mp4,application/pdf">
            <button class="btn btn-danger">Mở khiếu nại</button>
        </form>
    @endif
    @if ($order->canPayAgain())
        <a class="btn btn-accent" href="{{ route('user.orders.momo.pay', $order) }}">Thanh toán MoMo</a>
    @endif
    <h3>Giao dịch</h3>
    @foreach ($order->paymentTransactions as $tx)
        <p class="muted">{{ $tx->gateway }} · {{ $tx->status }} · {{ $tx->message }}</p>
    @endforeach
</div>
@endsection
