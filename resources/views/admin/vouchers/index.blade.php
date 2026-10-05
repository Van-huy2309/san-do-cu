@extends('layouts.admin')
@section('title', 'Phiếu giảm giá')
@section('content')
<h1>Phiếu giảm giá của sàn</h1>
<p class="muted">Số lượt là số đơn được dùng mã. Hết lượt thì khách không áp dụng được.</p>
<form method="post" action="{{ route('admin.vouchers.store') }}" class="panel" style="max-width:640px">
    @csrf
    <label>Mã</label>
    <input class="field" name="code" value="{{ old('code') }}" placeholder="RELIC10" required>
    <label>Tên phiếu</label>
    <input class="field" name="name" value="{{ old('name') }}" required>
    <label>Kiểu giảm</label>
    <select class="field" name="discount_type">
        <option value="percent" @selected(old('discount_type') === 'percent')>Phần trăm</option>
        <option value="fixed" @selected(old('discount_type', 'fixed') === 'fixed')>Số tiền (₫)</option>
    </select>
    <label>Mức giảm</label>
    <input class="field" type="number" name="discount_value" min="1" value="{{ old('discount_value', 50000) }}" required>
    <label>Đơn tối thiểu (₫)</label>
    <input class="field" type="number" name="min_order" min="0" value="{{ old('min_order', 0) }}">
    <label>Số lượng</label>
    <input class="field" type="number" name="quantity" min="1" value="{{ old('quantity', 20) }}" required>
    <label>Hết hạn</label>
    <input class="field" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}">
    <button class="btn btn-accent">Tạo phiếu</button>
</form>
<table class="table">
    <tr><th>Mã</th><th>Giảm</th><th>Đã dùng</th><th>Trạng thái</th><th></th></tr>
    @forelse ($vouchers as $voucher)
        <tr>
            <td><strong>{{ $voucher->code }}</strong><div class="muted">{{ $voucher->name }}</div></td>
            <td>{{ $voucher->discountLabel() }}</td>
            <td>{{ $voucher->used_count }} / {{ $voucher->quantity }}</td>
            <td>{{ $voucher->is_active ? 'Đang mở' : 'Đã tắt' }}</td>
            <td>
                <form method="post" action="{{ route('admin.vouchers.toggle', $voucher) }}">@csrf
                    <button class="btn btn-ghost btn-sm">{{ $voucher->is_active ? 'Tắt' : 'Bật' }}</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="5">Chưa có phiếu.</td></tr>
    @endforelse
</table>
@endsection
