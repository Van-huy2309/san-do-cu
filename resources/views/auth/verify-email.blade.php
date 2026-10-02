@extends('layouts.auth')
@section('title', 'Xác thực email')
@section('content')
<h2>Xác thực email</h2>
<p class="sub">
    Đã gửi mã 6 số tới <strong>{{ auth()->user()->email }}</strong>.
    Mở email trên điện thoại, rồi nhập mã tại đây — không cần bấm link localhost.
</p>
<form method="POST" action="{{ route('verification.confirm') }}">
    @csrf
    <div class="field-group">
        <label class="field-label" for="code">Mã xác thực</label>
        <input class="field field-input" type="text" name="code" id="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus>
        @error('code')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-accent btn-auth">Xác thực</button>
</form>
<form method="POST" action="{{ route('verification.send') }}" style="margin-top:12px">
    @csrf
    <button type="submit" class="btn btn-ghost btn-auth">Gửi lại mã</button>
</form>
<p class="auth-link"><a href="{{ route('home') }}">Để sau, về trang chủ</a></p>
@endsection
