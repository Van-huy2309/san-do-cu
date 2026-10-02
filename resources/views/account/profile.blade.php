@extends('layouts.account')
@section('title', 'Thông tin tài khoản')
@section('content')
<h1>Thông tin</h1>
<div class="panel info-list">
    <div class="info-row"><span class="muted">Tên hiển thị</span><strong>{{ $user->name }}</strong></div>
    <div class="info-row"><span class="muted">Email</span><strong>{{ $user->email }}</strong></div>
    <div class="info-row"><span class="muted">Số điện thoại</span><strong>{{ $user->phone ?: 'Chưa liên kết' }}</strong></div>
    <div class="info-row"><span class="muted">Mật khẩu</span><strong>••••••••</strong></div>
    <div class="info-row"><span class="muted">KYC</span><strong>{{ $user->kycLabel() }}</strong></div>
    <div class="info-row"><span class="muted">Uy tín</span><strong>{{ number_format($user->ratingScore(), 1) }}/5 · {{ $user->receivedReviews()->count() }} đánh giá · tham gia {{ $user->created_at->format('d/m/Y') }}</strong></div>
    <div class="info-row"><span class="muted">Ví</span><strong>{{ number_format($user->wallet_balance) }} ₫</strong></div>
</div>
@unless (auth()->user()->isAdmin())
<div class="card-actions" style="margin:16px 0">
    <a class="btn btn-accent" href="{{ route('user.orders.index') }}">Đơn mua</a>
    <a class="btn btn-ghost" href="{{ route('favorites.index') }}">Yêu thích</a>
    <a class="btn btn-ghost" href="{{ route('messages.index') }}">Tin nhắn</a>
</div>
@endunless
<a class="change-link" href="{{ route('account.kyc') }}">Định danh CCCD</a>
 · <a class="change-link" href="{{ route('account.change.index') }}">Thay đổi thông tin</a>
<p class="muted" style="font-size:.82rem">Mọi thay đổi đều cần mã xác thực gửi về email {{ $user->email }}. Email không thể đổi.</p>
@endsection
