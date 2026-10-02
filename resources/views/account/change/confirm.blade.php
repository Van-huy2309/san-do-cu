@extends('layouts.account')
@section('title', 'Xác thực đổi ' . mb_strtolower($label))
@section('content')
<div class="page-head">
    <form method="post" action="{{ route('account.change.cancel') }}">@csrf<button class="back-btn" type="submit">← Thoát</button></form>
    <h1>Xác thực đổi {{ mb_strtolower($label) }}</h1>
</div>
<div class="panel">
    <p>Giá trị mới: <strong>{{ $preview }}</strong></p>
    <p class="muted">Nhập mã 6 số vừa gửi về email và mật khẩu tài khoản để xác thực lần cuối.</p>
    <form method="post" action="{{ route('account.change.apply') }}">
        @csrf
        <label>Mã xác thực</label>
        <input class="field" name="code" inputmode="numeric" maxlength="6" required autofocus>
        <label>Mật khẩu tài khoản</label>
        <input class="field" type="password" name="current_password" required>
        @foreach ($errors->all() as $err)
            <div class="flash flash-err">{{ $err }}</div>
        @endforeach
        <button class="btn btn-accent">Đổi</button>
    </form>
    <form method="post" action="{{ route('account.change.resend') }}" style="margin-top:12px">
        @csrf<button class="linkish" type="submit">Gửi lại mã</button>
    </form>
</div>
<p class="muted" style="font-size:.82rem">Rời khỏi trang này hoặc bấm Thoát sẽ hủy toàn bộ thay đổi.</p>
@endsection
