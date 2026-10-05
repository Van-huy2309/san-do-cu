@extends('layouts.seller')
@section('title', 'Phiếu giảm giá')
@section('content')
<div class="meta" style="justify-content:space-between;align-items:center">
    <h1>Phiếu của shop</h1>
</div>
<p class="muted">Bạn đặt số lượt dùng. Khách chỉ được áp dụng khi còn lượt và giỏ có sản phẩm của shop.</p>
<form method="post" action="{{ route('seller.vouchers.store') }}" class="panel" style="max-width:640px">
    @csrf
    <label>Mã</label>
    <input class="field" name="code" value="{{ old('code') }}" placeholder="SHOP20" required>
    <label>Tên phiếu</label>
    <input class="field" name="name" value="{{ old('name') }}" required>
    <label>Kiểu giảm</label>
    <select class="field" name="discount_type">
        <option value="percent" @selected(old('discount_type') === 'percent')>Phần trăm</option>
        <option value="fixed" @selected(old('discount_type', 'fixed') === 'fixed')>Số tiền (₫)</option>
    </select>
    <label>Mức giảm</label>
    <input class="field" type="number" name="discount_value" min="1" value="{{ old('discount_value', 30000) }}" required>
    <label>Đơn tối thiểu (₫)</label>
    <input class="field" type="number" name="min_order" min="0" value="{{ old('min_order', 0) }}">
    <label>Số lượng</label>
    <input class="field" type="number" name="quantity" min="1" value="{{ old('quantity', 10) }}" required>
    <label>Hết hạn</label>
    <input class="field" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}">
    <button class="btn btn-accent">Tạo phiếu</button>
</form>
@foreach ($vouchers as $voucher)
    <div class="panel" style="margin-top:10px">
        <strong>{{ $voucher->code }}</strong>
        <span class="badge">{{ $voucher->discountLabel() }}</span>
        <span class="badge">{{ $voucher->used_count }} / {{ $voucher->quantity }} lượt</span>
        @unless ($voucher->is_active) <span class="badge">Đã tắt</span> @endunless
        <p class="muted">{{ $voucher->name }}@if ($voucher->ends_at) · hết hạn {{ $voucher->ends_at->format('d/m/Y') }}@endif</p>
        <form method="post" action="{{ route('seller.vouchers.toggle', $voucher) }}">@csrf
            <button class="btn btn-ghost btn-sm">{{ $voucher->is_active ? 'Tắt' : 'Bật' }}</button>
        </form>
    </div>
@endforeach
@endsection
