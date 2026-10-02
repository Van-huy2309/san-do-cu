@extends('layouts.auth')
@section('title', 'Quên mật khẩu')
@section('content')
<h2>Quên mật khẩu</h2>
<p class="sub">Nhập email tài khoản Relic để nhận link đặt lại mật khẩu.</p>
<form method="post" action="{{ route('password.email') }}">
    @csrf
    <div class="field-group">
        <label class="field-label" for="email">Email</label>
        <input class="field field-input" type="email" name="email" id="email" value="{{ old('email') }}" required>
        @error('email')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <button class="btn btn-accent btn-auth">Gửi link</button>
</form>
<p class="auth-link"><a href="{{ route('login') }}">Quay lại đăng nhập</a></p>
@endsection
