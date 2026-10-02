<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Relic') — Chợ đồ điện tử cũ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ url('css/relic.css') }}?v=shop5">
    <link rel="stylesheet" href="{{ url('css/relic-magic.css') }}?v=visible">
    @stack('styles')
</head>
@php $isMall = request()->routeIs('home') || request()->routeIs('listings.index'); @endphp
<body class="{{ $isMall ? 'is-mall' : '' }}">
@if ($isMall)
<div class="site-sticky">
    <div class="mini">
        <div class="wrap mini-inner">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-on' : '' }}">Home</a>
            <a href="{{ route('listings.index') }}" class="{{ request()->routeIs('listings.index') ? 'is-on' : '' }}">Chợ</a>
            @unless (auth()->user()?->isAdmin())
            <a href="{{ route('seller.listings.create') }}">Kênh người bán</a>
            @endunless
            <span class="mini-spacer"></span>
            <form class="area-quick" action="{{ route('area.store') }}" method="get">
                <input type="hidden" name="redirect" value="{{ url()->full() }}">
                @include('partials.area-picker', ['pickerName' => 'area', 'pickerSubmit' => true])
            </form>
            @auth
                @unless (auth()->user()->isAdmin())
                <a href="{{ route('favorites.index') }}">Yêu thích</a>
                @endunless
                <button type="button" class="ai-nav-link" data-ai-open>@include('ai.icon') AI Care</button>
            @else
                <a href="{{ route('register') }}">Đăng ký</a>
                <a href="{{ route('login') }}">Đăng nhập</a>
            @endauth
        </div>
    </div>
    <div class="shop-head">
        <div class="wrap shop-head-inner">
            <a class="brand" href="{{ route('home') }}"><span class="brand-mark">R</span> Relic</a>
            <form class="search search-lg mall-searchbar" action="{{ route('listings.index') }}" method="get">
                <input name="q" value="{{ request('q') }}" placeholder="Tìm sản phẩm..." aria-label="Tìm kiếm">
                @include('partials.area-picker', ['pickerName' => 'city'])
                <button type="submit">Tìm kiếm</button>
            </form>
            @unless (auth()->user()?->isAdmin())
            <a class="cart-btn" href="{{ route('user.cart.index') }}">Giỏ <b>{{ $cartCount ?? 0 }}</b></a>
            @else
            <a class="cart-btn" href="{{ route('admin.dashboard') }}">Quản trị</a>
            @endunless
            @auth
                <button type="button" class="ai-head-btn" data-ai-open title="Relic Care" aria-label="Mở Relic Care">@include('ai.icon', ['size' => 18])</button>
                <a class="user-chip" href="{{ route('account.profile') }}">
                    <span class="avatar">{{ auth()->user()->initials() }}</span>
                    <span>{{ Str::limit(auth()->user()->name, 14) }}</span>
                </a>
            @endauth
        </div>
    </div>
    @if (isset($navCategories) && $navCategories->isNotEmpty())
        <div class="cat-rail-wrap">
            <div class="wrap cat-rail-box">
                <button type="button" class="cat-arrow" data-dir="-1" aria-label="Danh mục trước">‹</button>
                <div class="cat-rail" id="cat-rail">
                    @foreach ($navCategories as $navCat)
                        <a class="cat-chip {{ request('category') == $navCat->id ? 'is-on' : '' }}" href="{{ route('listings.index', ['category' => $navCat->id]) }}">
                            <span>{{ $navCat->icon }}</span>{{ $navCat->name }}
                        </a>
                    @endforeach
                </div>
                <button type="button" class="cat-arrow" data-dir="1" aria-label="Danh mục sau">›</button>
            </div>
            <div class="wrap">
                <input type="range" class="cat-slider" id="cat-slider" min="0" max="1000" value="0" step="1" aria-label="Kéo để xem danh mục">
            </div>
        </div>
    @endif
</div>
@else
<div class="page-top">
    <div class="wrap page-top-inner">
        <a class="back-btn" href="{{ request()->routeIs('user.cart.index') ? route('home') : (url()->previous() !== url()->current() ? url()->previous() : route('home')) }}">← Thoát</a>
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark">R</span> Relic</a>
        <span class="mini-spacer"></span>
        @auth
            <button type="button" class="ai-head-btn" data-ai-open title="Relic Care" aria-label="Mở Relic Care">@include('ai.icon', ['size' => 18])</button>
        @endauth
        @unless (auth()->user()?->isAdmin())
        <a class="cart-btn" href="{{ route('user.cart.index') }}">Giỏ <b>{{ $cartCount ?? 0 }}</b></a>
        @else
        <a class="cart-btn" href="{{ route('admin.dashboard') }}">Quản trị</a>
        @endunless
        @auth
            <a class="user-chip" href="{{ route('account.profile') }}">
                <span class="avatar">{{ auth()->user()->initials() }}</span>
                <span>{{ Str::limit(auth()->user()->name, 14) }}</span>
            </a>
        @else
            <a href="{{ route('login') }}">Đăng nhập</a>
        @endauth
    </div>
</div>
@endif
<main id="main" class="wrap">
    @if (session('success')) <div class="flash flash-ok">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="flash flash-err">{{ session('error') }}</div> @endif
    @if (session('warning')) <div class="flash flash-warn">{{ session('warning') }}</div> @endif
    @if (session('message')) <div class="flash flash-ok">{{ session('message') }}</div> @endif
    @yield('content')
</main>
<footer class="footer">
    <div class="wrap footer-grid">
        <div>
            <div class="brand"><span class="brand-mark">R</span> Relic</div>
            <p class="muted">Sàn trung gian đồ điện tử đã qua sử dụng.</p>
        </div>
        <div>
            <strong>Mua sắm</strong>
            <p><a href="{{ route('home') }}">Home</a></p>
            <p><a href="{{ route('listings.index') }}">Chợ</a></p>
            <p><a href="{{ route('listings.index', ['verified' => 1]) }}">Relic Seal</a></p>
        </div>
        <div>
            <strong>Bán hàng</strong>
            @unless (auth()->user()?->isAdmin())
            <p><a href="{{ route('account.kyc') }}">KYC</a></p>
            <p><a href="{{ route('seller.listings.create') }}">Đăng tin</a></p>
            @endunless
            @if (auth()->user()?->isAdmin())
            <p><a href="{{ route('admin.dashboard') }}">Quản trị</a></p>
            @endif
        </div>
        <div>
            <strong>Pháp lý</strong>
            <p><a href="{{ route('pages.show', 'how-it-works') }}">Cách hoạt động</a></p>
            <p><a href="{{ route('pages.show', 'terms') }}">Điều khoản</a></p>
        </div>
    </div>
</footer>
<script>
(function () {
    const fold = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const pickers = document.querySelectorAll('[data-area-picker]');
    if (!pickers.length) return;

    function closeAll() {
        pickers.forEach((picker) => {
            picker.querySelector('.area-picker-menu').hidden = true;
            picker.querySelector('.area-picker-btn').setAttribute('aria-expanded', 'false');
        });
    }

    pickers.forEach((picker) => {
        const btn = picker.querySelector('.area-picker-btn');
        const menu = picker.querySelector('.area-picker-menu');
        const label = picker.querySelector('[data-area-label]');
        const input = picker.querySelector('[data-area-input]');
        const find = picker.querySelector('.area-picker-find');
        const options = picker.querySelectorAll('.area-picker-opt');

        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const willOpen = menu.hidden;
            closeAll();
            if (!willOpen) return;
            menu.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            find.value = '';
            options.forEach((opt) => { opt.hidden = false; });
            find.focus();
        });

        find.addEventListener('input', () => {
            const query = fold(find.value.trim());
            options.forEach((opt) => {
                opt.hidden = query !== '' && !fold(opt.textContent).includes(query);
            });
        });
        find.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') event.preventDefault();
        });

        options.forEach((opt) => {
            opt.addEventListener('click', () => {
                input.value = opt.dataset.value || '';
                label.textContent = opt.textContent.trim();
                options.forEach((item) => item.classList.toggle('is-on', item === opt));
                menu.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
                if (picker.classList.contains('is-quick')) picker.closest('form')?.requestSubmit();
            });
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-area-picker]')) closeAll();
    });
})();
</script>
<script>
(function () {
    const rail = document.getElementById('cat-rail');
    if (!rail) return;
    const slider = document.getElementById('cat-slider');
    const maxScroll = () => Math.max(1, rail.scrollWidth - rail.clientWidth);

    function syncSlider() {
        if (slider) slider.value = String(Math.round(rail.scrollLeft / maxScroll() * 1000));
    }
    if (slider) {
        slider.addEventListener('input', () => {
            rail.scrollLeft = Number(slider.value) / 1000 * maxScroll();
        });
        rail.addEventListener('scroll', syncSlider, { passive: true });
        window.addEventListener('resize', syncSlider);
        syncSlider();
    }

    document.querySelectorAll('.cat-arrow').forEach(btn => {
        btn.addEventListener('click', () => {
            rail.scrollBy({ left: Number(btn.dataset.dir) * 240, behavior: 'smooth' });
        });
    });
    let down = false, dragging = false, startX = 0, startLeft = 0, pointerId = null;
    rail.addEventListener('pointerdown', (e) => {
        if (e.button !== 0) return;
        down = true;
        dragging = false;
        startX = e.clientX;
        startLeft = rail.scrollLeft;
        pointerId = e.pointerId;
    });
    rail.addEventListener('pointermove', (e) => {
        if (!down) return;
        const dx = e.clientX - startX;
        // Only take over the pointer once it is clearly a drag, so taps still open the category.
        if (!dragging && Math.abs(dx) > 5) {
            dragging = true;
            rail.setPointerCapture(pointerId);
            rail.classList.add('is-drag');
        }
        if (dragging) rail.scrollLeft = startLeft - dx;
    });
    const stop = () => {
        down = false;
        rail.classList.remove('is-drag');
        if (pointerId !== null && rail.hasPointerCapture(pointerId)) rail.releasePointerCapture(pointerId);
        pointerId = null;
    };
    rail.addEventListener('pointerup', stop);
    rail.addEventListener('pointercancel', stop);
    rail.addEventListener('click', (e) => {
        if (dragging) {
            e.preventDefault();
            dragging = false;
        }
    }, true);
})();
</script>
<script>
(function () {
    if (!navigator.geolocation) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const onceKey = 'relic.geo.asked';
    function save(lat, lng) {
        fetch(@json(route('location.store')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: JSON.stringify({ lat, lng })
        });
        document.querySelectorAll('[data-geo-lat]').forEach(i => i.value = lat);
        document.querySelectorAll('[data-geo-lng]').forEach(i => i.value = lng);
    }
    document.querySelectorAll('[data-geo]').forEach(btn => {
        btn.addEventListener('click', () => {
            navigator.geolocation.getCurrentPosition(p => save(p.coords.latitude, p.coords.longitude));
        });
    });
    if (!sessionStorage.getItem(onceKey)) {
        sessionStorage.setItem(onceKey, '1');
        navigator.geolocation.getCurrentPosition(p => save(p.coords.latitude, p.coords.longitude), () => {}, { maximumAge: 600000, timeout: 4000 });
    }
})();
</script>
@auth
@include('ai.care')
@endauth
@stack('scripts')
</body>
</html>
