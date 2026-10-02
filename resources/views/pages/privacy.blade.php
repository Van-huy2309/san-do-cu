@extends('layouts.store')
@section('title', 'Bảo mật')
@section('content')
<div class="panel">
    <h1>Chính sách bảo mật</h1>
    <p>Relic dùng CSRF, hash mật khẩu, throttle đăng nhập, header bảo mật, HMAC MoMo, lockForUpdate khi hoàn tất thanh toán. Serial máy được băm. Không tin giá/phí từ trình duyệt.</p>
</div>
@endsection
