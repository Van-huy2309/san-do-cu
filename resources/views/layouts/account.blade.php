<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.theme-boot')
    <title>@yield('title', 'Tài khoản') — Relic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ url('css/relic.css') }}">
</head>
<body class="acct-body">
<div class="acct-top">
    <a class="back-btn" href="{{ route('home') }}" title="Thoát về trang chủ">← Thoát</a>
    <strong>Tài khoản</strong>
</div>
<div class="acct-shell">
    <aside class="acct-side">
        <div class="acct-user">
            <span class="avatar">{{ auth()->user()->initials() }}</span>
            <div>
                <strong>{{ auth()->user()->name }}</strong>
                <div class="muted" style="font-size:.78rem">{{ auth()->user()->email }}</div>
            </div>
        </div>
        <a href="{{ route('account.profile') }}" class="{{ request()->routeIs('account.profile') || request()->routeIs('account.change.*') ? 'is-on' : '' }}">Thông tin</a>
        @unless (auth()->user()->isAdmin())
        <a href="{{ route('favorites.index') }}" class="{{ request()->routeIs('favorites.*') ? 'is-on' : '' }}">Yêu thích</a>
        <a href="{{ route('user.orders.index') }}" class="{{ request()->routeIs('user.orders.*') ? 'is-on' : '' }}">Đơn mua</a>
        <a href="{{ route('messages.index') }}" class="{{ request()->routeIs('messages.*') ? 'is-on' : '' }}">Tin nhắn</a>
        <a href="{{ route('support.index') }}" class="{{ request()->routeIs('support.*') ? 'is-on' : '' }}">Chat với admin</a>
        <a href="{{ route('seller.listings.index') }}">Kênh người bán</a>
        @endunless
        <button type="button" class="ai-nav-link" data-ai-open>@include('ai.icon') AI Care</button>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.dashboard') }}">Quản trị</a>
        @endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="linkish acct-out" type="submit">Đăng xuất</button></form>
    </aside>
    <div class="acct-main">
        @if (session('success')) <div class="flash flash-ok">{{ session('success') }}</div> @endif
        @if (session('error')) <div class="flash flash-err">{{ session('error') }}</div> @endif
        @if (session('warning')) <div class="flash flash-warn">{{ session('warning') }}</div> @endif
        @if ($errors->any())
            <div class="flash flash-err">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </div>
</div>
@include('ai.care')
@stack('scripts')
<script>
(function () {
    if (!navigator.geolocation) {
        document.querySelectorAll('[data-geo]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const status = btn.parentElement?.querySelector('[data-geo-status]');
                if (status) status.textContent = 'Trình duyệt này không hỗ trợ lấy vị trí.';
            });
        });
        return;
    }
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    document.querySelectorAll('[data-geo]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const status = btn.parentElement?.querySelector('[data-geo-status]');
            if (status) status.textContent = 'Đang lấy vị trí…';
            navigator.geolocation.getCurrentPosition((p) => {
                document.querySelectorAll('[data-geo-lat]').forEach(i => i.value = p.coords.latitude);
                document.querySelectorAll('[data-geo-lng]').forEach(i => i.value = p.coords.longitude);
                const status = btn.parentElement?.querySelector('[data-geo-status]');
                if (status) status.textContent = 'Đã lấy vị trí máy.';
                fetch(@json(url('/vi-tri')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: JSON.stringify({ lat: p.coords.latitude, lng: p.coords.longitude })
                }).catch(() => {});
            }, (err) => {
                const status = btn.parentElement?.querySelector('[data-geo-status]');
                if (!status) return;
                status.textContent = err.code === 1
                    ? 'Trình duyệt đang chặn vị trí. Bấm biểu tượng ổ khóa trên thanh địa chỉ và cho phép Location.'
                    : 'Không lấy được vị trí máy. Tin vẫn đăng được theo tỉnh/thành bạn chọn.';
            }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 120000 });
        });
    });
})();
</script>
</body>
</html>
