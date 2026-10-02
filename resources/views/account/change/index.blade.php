@extends('layouts.account')
@section('title', 'Thay đổi thông tin')
@section('content')
<div class="page-head">
    <a class="back-btn" href="{{ route('account.profile') }}">← Thoát</a>
    <h1>Chọn mục muốn đổi</h1>
</div>
<p class="muted">Mỗi lần chỉ đổi được một mục. Thoát giữa chừng thì mọi thay đổi bị hủy.</p>
<div class="choice-grid">
    @foreach (\App\Http\Controllers\AccountController::FIELDS as $key => $label)
        <a class="choice" href="{{ route('account.change.form', $key) }}">
            <strong>{{ $label }}</strong>
            <span class="muted">Xác thực bằng mã gửi về email</span>
        </a>
    @endforeach
</div>
@endsection
