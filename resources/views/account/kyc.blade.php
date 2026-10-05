@extends('layouts.seller')
@section('title', 'Định danh KYC')
@section('content')
<h1>Định danh người bán</h1>
<p class="muted">Bắt buộc xác minh CCCD trước khi đăng tin. Relic không lưu đủ số CCCD — chỉ 4 số cuối.</p>
<div class="panel">
    <p>Trạng thái: <strong>{{ $user->kycLabel() }}</strong></p>
    @if ($user->kyc_note)
        <p class="muted">Ghi chú: {{ $user->kyc_note }}</p>
    @endif
    @if ($user->kyc_status === 'verified')
        <p>Đã duyệt{{ $user->kyc_id_last4 ? ' · ****' . $user->kyc_id_last4 : '' }}.</p>
        <a class="btn btn-accent" href="{{ route('seller.listings.create') }}">Đăng tin</a>
    @elseif ($user->kyc_status === 'pending')
        <p>Hồ sơ đang chờ admin duyệt.</p>
    @else
        <form method="post" action="{{ route('account.kyc.store') }}" enctype="multipart/form-data">
            @csrf
            <label>Họ tên trên CCCD</label>
            <input class="field" name="kyc_full_name" value="{{ old('kyc_full_name', $user->kyc_full_name ?: $user->name) }}" required>
            <label>Số CCCD / CMND</label>
            <input class="field" name="id_number" inputmode="numeric" required>
            <label>Ảnh mặt trước</label>
            <input class="field" type="file" name="kyc_front" accept="image/*" required>
            <label>Ảnh mặt sau</label>
            <input class="field" type="file" name="kyc_back" accept="image/*" required>
            <button class="btn btn-accent">Gửi hồ sơ</button>
        </form>
        @if ($errors->any())
            <p class="muted" style="color:var(--danger)">{{ $errors->first() }}</p>
        @endif
    @endif
</div>
@endsection
