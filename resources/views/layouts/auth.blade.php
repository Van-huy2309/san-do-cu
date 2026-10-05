<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.theme-boot')
    <title>@yield('title', 'Đăng nhập') — Relic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ url('css/relic.css') }}?v=auth6">
    <link rel="stylesheet" href="{{ url('css/three-d-paper.css') }}?v=auth3">
</head>
@php
    $paperMode = request()->routeIs('register') ? 'register' : 'login';
    $paperName = old('name', '');
    $paperEmail = old('email', '');
    $paperSrc = url('threeui/3d-paper-relic.html').'?v=hello1&mode='.$paperMode.'&name='.rawurlencode($paperName).'&email='.rawurlencode($paperEmail);
@endphp
<body class="auth-body">
    <div class="auth-split">
        <aside class="auth-paper" aria-hidden="false">
            <div class="shader-frame">
                <div
                    class="threeui-background three-d-paper"
                    id="three-d-paper"
                    role="group"
                    aria-label="Tờ giấy 3D Relic — kéo để xoay"
                    data-variant="original"
                    data-state="loading"
                    data-mode="{{ $paperMode }}"
                    data-name="{{ $paperName }}"
                    data-email="{{ $paperEmail }}"
                    data-src="{{ $paperSrc }}"
                    data-title="Relic"
                ></div>
            </div>
        </aside>
        <main class="auth-shell">
            <a class="brand auth-logo" href="{{ route('home') }}"><span class="brand-mark">R</span> Relic</a>
            <div class="auth-card">
                @if (session('success')) <div class="flash flash-ok">{{ session('success') }}</div> @endif
                @if (session('error')) <div class="flash flash-err">{{ session('error') }}</div> @endif
                @if (session('status')) <div class="flash flash-ok">{{ session('status') }}</div> @endif
                @if (session('message')) <div class="flash flash-ok">{{ session('message') }}</div> @endif
                @yield('content')
            </div>
            <nav class="auth-links">
                <a href="{{ route('login') }}" class="{{ request()->routeIs('login') ? 'is-on' : '' }}">Đăng nhập</a>
                <a href="{{ route('register') }}" class="{{ request()->routeIs('register') ? 'is-on' : '' }}">Đăng ký</a>
                <a href="{{ route('password.request') }}" class="{{ request()->routeIs('password.*') ? 'is-on' : '' }}">Quên mật khẩu</a>
            </nav>
        </main>
    </div>
    <script src="{{ url('js/three-d-paper.js') }}?v=auth4" defer></script>
    <script>
    document.querySelectorAll('[data-pw-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = btn.parentElement.querySelector('input');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Ẩn' : 'Hiện';
            btn.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        });
    });
    </script>
</body>
</html>
