@extends('layouts.account')
@section('title', 'Đổi ' . mb_strtolower($label))
@section('content')
<div class="page-head">
    <a class="back-btn" href="{{ route('account.change.index') }}">← Thoát</a>
    <h1>Đổi {{ mb_strtolower($label) }}</h1>
</div>
<form class="panel" method="post" action="{{ route('account.change.start', $field) }}">
    @csrf
    @if ($field === 'name')
        <label>Tên hiển thị mới</label>
        <input class="field" name="name" value="{{ old('name') }}" maxlength="80" required autofocus>
    @elseif ($field === 'phone')
        <label>Số điện thoại mới</label>
        <input class="field" name="phone" value="{{ old('phone') }}" placeholder="0xxxxxxxxx" required autofocus>
    @else
        <label>Mật khẩu hiện tại</label>
        <input class="field" type="password" name="current_password" required autofocus>
        <label>Mật khẩu mới</label>
        <input class="field" type="password" name="password" minlength="8" required>
        <label>Nhập lại mật khẩu mới</label>
        <input class="field" type="password" name="password_confirmation" required>
    @endif
    @foreach ($errors->all() as $err)
        <div class="flash flash-err">{{ $err }}</div>
    @endforeach
    <button class="btn btn-accent">Gửi mã xác thực</button>
    <a class="btn btn-ghost" href="{{ route('account.profile') }}">Hủy</a>
</form>
@endsection
