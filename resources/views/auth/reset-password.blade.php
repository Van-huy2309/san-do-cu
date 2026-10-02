@extends('layouts.auth')
@section('title', 'Đặt lại mật khẩu')
@section('content')
<h2>Đặt lại mật khẩu</h2>
<form method="post" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="field-group">
        <label class="field-label" for="email">Email</label>
        <input class="field field-input" type="email" name="email" id="email" value="{{ old('email', $email) }}" required>
    </div>
    <div class="field-group">
        <label class="field-label" for="password">Mật khẩu mới</label>
        <div class="pw-wrap">
            <input class="field field-input" type="password" name="password" id="password" required minlength="8">
            <button type="button" class="pw-toggle" data-pw-toggle aria-label="Hiện mật khẩu">Hiện</button>
        </div>
    </div>
    <div class="field-group">
        <label class="field-label" for="password_confirmation">Nhập lại mật khẩu</label>
        <div class="pw-wrap">
            <input class="field field-input" type="password" name="password_confirmation" id="password_confirmation" required>
            <button type="button" class="pw-toggle" data-pw-toggle aria-label="Hiện mật khẩu">Hiện</button>
        </div>
    </div>
    <button class="btn btn-accent btn-auth">Cập nhật mật khẩu</button>
</form>
@endsection
