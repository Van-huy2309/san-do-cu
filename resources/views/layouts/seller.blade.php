<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.theme-boot')
    <title>@yield('title', 'Kênh người bán') — Relic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ url('css/relic.css') }}">
</head>
<body class="acct-body">
<div class="acct-top">
    <a class="back-btn" href="{{ route('home') }}" title="Về chợ">← Về chợ</a>
    <strong>Kênh người bán</strong>
</div>
<div class="acct-shell">
    <aside class="acct-side">
        <div class="acct-user">
            <span class="avatar">{{ auth()->user()->initials() }}</span>
            <div>
                <strong>{{ auth()->user()->name }}</strong>
                <div class="muted" style="font-size:.78rem">Quản lý shop</div>
            </div>
        </div>
        <a href="{{ route('seller.listings.index') }}" class="{{ request()->routeIs('seller.listings.index') ? 'is-on' : '' }}">Sản phẩm</a>
        <a href="{{ route('seller.listings.create') }}" class="{{ request()->routeIs('seller.listings.create') || request()->routeIs('seller.listings.edit') ? 'is-on' : '' }}">Đăng tin</a>
        <a href="{{ route('seller.vouchers.index') }}" class="{{ request()->routeIs('seller.vouchers.*') ? 'is-on' : '' }}">Phiếu của shop</a>
        <a href="{{ route('seller.bank') }}" class="{{ request()->routeIs('seller.bank') ? 'is-on' : '' }}">Tài khoản ngân hàng</a>
        <a href="{{ route('seller.reviews.index') }}" class="{{ request()->routeIs('seller.reviews.*') ? 'is-on' : '' }}">Phản hồi</a>
        <a href="{{ route('seller.earnings') }}" class="{{ request()->routeIs('seller.earnings*') ? 'is-on' : '' }}">Thu chi</a>
        <a href="{{ route('account.kyc') }}" class="{{ request()->routeIs('account.kyc') ? 'is-on' : '' }}">KYC</a>
        <a href="{{ route('account.profile') }}">Tài khoản mua hàng</a>
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
@stack('scripts')
<script>
(function () {
    if (!navigator.geolocation) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    document.querySelectorAll('[data-geo]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const status = btn.parentElement?.querySelector('[data-geo-status]');
            if (status) status.textContent = 'Đang lấy vị trí…';
            navigator.geolocation.getCurrentPosition((p) => {
                document.querySelectorAll('[data-geo-lat]').forEach(i => i.value = p.coords.latitude);
                document.querySelectorAll('[data-geo-lng]').forEach(i => i.value = p.coords.longitude);
                if (status) status.textContent = 'Đã lấy vị trí máy.';
                fetch(@json(url('/vi-tri')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: JSON.stringify({ lat: p.coords.latitude, lng: p.coords.longitude })
                }).catch(() => {});
            }, () => { if (status) status.textContent = 'Không lấy được vị trí. Tin vẫn đăng được theo tỉnh bạn chọn.'; });
        });
    });
})();
</script>
</body>
</html>
