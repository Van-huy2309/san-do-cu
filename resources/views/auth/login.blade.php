@extends('layouts.auth')
@section('title', 'Đăng nhập')
@section('content')
<h2>Đăng nhập</h2>
<p class="sub">Chào mừng trở lại Relic. Dùng email và mật khẩu để vào tài khoản.</p>
<form action="{{ route('login') }}" method="POST">
    @csrf
    <div class="field-group">
        <label class="field-label" for="email">Email</label>
        <input type="email" name="email" id="email" class="field field-input" value="{{ old('email') }}" required autofocus>
        @error('email')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="field-group">
        <label class="field-label" for="password">Mật khẩu</label>
        <div class="pw-wrap">
            <input type="password" name="password" id="password" class="field field-input" required>
            <button type="button" class="pw-toggle" data-pw-toggle aria-label="Hiện mật khẩu">Hiện</button>
        </div>
        @error('password')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <label class="remember-row">
        <input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập
    </label>
    <button type="submit" class="btn btn-accent btn-auth">Đăng nhập</button>
</form>
<p class="auth-link">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký</a></p>
<p class="auth-link"><a href="{{ route('password.request') }}">Quên mật khẩu?</a></p>
@endsection
