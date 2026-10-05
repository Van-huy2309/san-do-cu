@extends('layouts.admin')
@section('title', 'Marketing')
@section('content')
<h1>Gói marketing</h1>
<p class="muted">Mỗi gói có số suất cố định. Người bán đăng ký một tin vào gói thì tin hiện ở mục Quảng cáo trên trang chủ. Hết suất thì không đăng ký thêm được.</p>
<form method="post" action="{{ route('admin.marketing.store') }}" class="panel" style="max-width:640px">
    @csrf
    <label>Tên gói</label>
    <input class="field" name="name" value="{{ old('name') }}" placeholder="Tuần nổi bật" required>
    <label>Mô tả</label>
    <input class="field" name="description" value="{{ old('description') }}" placeholder="Hiện trên trang chủ">
    <label>Số sản phẩm tối đa</label>
    <input class="field" type="number" name="slot_limit" min="1" value="{{ old('slot_limit', 8) }}" required>
    <label>Số ngày hiện quảng cáo</label>
    <input class="field" type="number" name="duration_days" min="1" value="{{ old('duration_days', 7) }}" required>
    <button class="btn btn-accent">Tạo gói</button>
</form>
<table class="table">
    <tr><th>Gói</th><th>Suất</th><th>Thời hạn</th><th>Trạng thái</th><th></th></tr>
    @forelse ($packages as $package)
        <tr>
            <td><strong>{{ $package->name }}</strong><div class="muted">{{ $package->description }}</div></td>
            <td>{{ $package->taken_slots }} / {{ $package->slot_limit }}</td>
            <td>{{ $package->duration_days }} ngày</td>
            <td>{{ $package->is_active ? 'Đang mở' : 'Đã đóng' }}</td>
            <td>
                <form method="post" action="{{ route('admin.marketing.toggle', $package) }}">@csrf
                    <button class="btn btn-ghost btn-sm">{{ $package->is_active ? 'Đóng' : 'Mở' }}</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="5">Chưa có gói.</td></tr>
    @endforelse
</table>
<h2>Đăng ký đang chạy</h2>
<table class="table">
    <tr><th>Gói</th><th>Tin</th><th>Shop</th><th>Hết hạn</th></tr>
    @forelse ($enrollments as $row)
        <tr>
            <td>{{ $row->package->name }}</td>
            <td>{{ $row->listing->title }}</td>
            <td>{{ $row->seller->name }}</td>
            <td>{{ $row->ends_at->format('d/m/Y H:i') }}</td>
        </tr>
    @empty
        <tr><td colspan="4">Chưa có sản phẩm đăng ký.</td></tr>
    @endforelse
</table>
@endsection
