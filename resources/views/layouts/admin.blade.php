<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Relic — @yield('title', 'Bảng điều khiển')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700&family=Sora:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/relic.css') }}">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-side">
        <a class="brand" href="{{ route('admin.dashboard') }}"><span class="brand-mark">R</span> Relic Admin</a>
        <p class="muted">Kiểm duyệt & vận hành</p>
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-on' : '' }}">Tổng quan</a>
        <a href="{{ route('admin.analytics') }}" class="{{ request()->routeIs('admin.analytics*') ? 'is-on' : '' }}">Doanh thu</a>
        <a href="{{ route('admin.listings') }}" class="{{ request()->routeIs('admin.listings*') ? 'is-on' : '' }}">Tin đăng</a>
        <a href="{{ route('admin.orders') }}" class="{{ request()->routeIs('admin.orders*') ? 'is-on' : '' }}">Đơn hàng</a>
        <a href="{{ route('admin.chat.index') }}" class="{{ request()->routeIs('admin.chat*') ? 'is-on' : '' }}">Chat khách</a>
        <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users*') ? 'is-on' : '' }}">Người dùng</a>
        <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports*') ? 'is-on' : '' }}">Báo cáo tin</a>
        <a href="{{ route('admin.kyc') }}" class="{{ request()->routeIs('admin.kyc*') ? 'is-on' : '' }}">KYC</a>
        <a href="{{ route('admin.disputes') }}" class="{{ request()->routeIs('admin.disputes*') ? 'is-on' : '' }}">Khiếu nại</a>
        <a href="{{ route('admin.finance') }}" class="{{ request()->routeIs('admin.finance*') ? 'is-on' : '' }}">Tài chính</a>
        <a href="{{ route('admin.categories') }}" class="{{ request()->routeIs('admin.categories*') ? 'is-on' : '' }}">Danh mục</a>
        <button type="button" class="ai-nav-link" data-ai-open>@include('ai.icon') AI Ops</button>
        <a href="{{ route('home') }}">Về sàn</a>
        <form method="post" action="{{ route('logout') }}" style="margin-top:16px;">@csrf<button class="btn btn-ghost btn-sm">Đăng xuất</button></form>
    </aside>
    <div class="admin-main">
        @if (session('success')) <div class="flash flash-ok">{{ session('success') }}</div> @endif
        @if (session('error')) <div class="flash flash-err">{{ session('error') }}</div> @endif
        @if ($errors->any())
            <div class="flash flash-err">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </div>
</div>
@stack('scripts')
@include('ai.ops')
</body>
</html>
