@extends('layouts.auth')
@section('title', 'Đăng ký')
@section('content')
<h2>Tạo tài khoản</h2>
<p class="sub">Đăng ký bằng email. Xác thực email chỉ cần khi bạn mua hàng hoặc đăng bán.</p>
<form action="{{ route('register') }}" method="POST">
    @csrf
    <div class="field-group">
        <label class="field-label" for="name">Họ tên</label>
        <input type="text" name="name" id="name" class="field field-input" value="{{ old('name') }}" required>
        @error('name')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="field-group">
        <label class="field-label" for="email">Email</label>
        <input type="email" name="email" id="email" class="field field-input" value="{{ old('email') }}" required>
        @error('email')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="field-group">
        <label class="field-label" for="password">Mật khẩu</label>
        <div class="pw-wrap">
            <input type="password" name="password" id="password" class="field field-input" required minlength="8">
            <button type="button" class="pw-toggle" data-pw-toggle aria-label="Hiện mật khẩu">Hiện</button>
        </div>
        @error('password')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="field-group">
        <label class="field-label" for="password_confirmation">Xác nhận mật khẩu</label>
        <div class="pw-wrap">
            <input type="password" name="password_confirmation" id="password_confirmation" class="field field-input" required minlength="8">
            <button type="button" class="pw-toggle" data-pw-toggle aria-label="Hiện mật khẩu">Hiện</button>
        </div>
    </div>
    <button type="submit" class="btn btn-accent btn-auth">Đăng ký</button>
</form>
<p class="auth-link">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
@endsection
