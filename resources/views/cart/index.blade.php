@extends('layouts.store')
@section('title', 'Giỏ hàng')
@section('content')
<h1>Giỏ hàng</h1>
@if (empty($cart))
    <p class="empty">Chưa có máy nào. <a href="{{ route('listings.index') }}">Vào chợ</a></p>
@else
    <div class="panel">
        @foreach ($cart as $key => $item)
            <div class="meta" style="justify-content:space-between;align-items:center;margin-bottom:12px">
                <div>
                    <strong>{{ $item['name'] }}</strong>
                    <div class="muted">{{ number_format($item['price'], 0, ',', '.') }} ₫ · 1 máy</div>
                </div>
                <form method="post" action="{{ route('user.cart.remove', $key) }}">@csrf @method('delete')<button class="btn btn-ghost btn-sm">Xóa</button></form>
            </div>
        @endforeach
        <p><strong>Tạm tính: {{ number_format($totalPrice, 0, ',', '.') }} ₫</strong></p>
        <a class="btn btn-accent" href="{{ route('user.payment.index') }}">Thanh toán ngay</a>
    </div>
@endif
@endsection
