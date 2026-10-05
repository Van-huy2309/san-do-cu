@extends('layouts.store')
@section('title', 'Chợ Relic')
@section('content')
@php
    $marqueeItems = collect($featured ?? [])
        ->concat($suggested ?? [])
        ->unique('id')
        ->take(12)
        ->values();
@endphp

<section class="mall-hero">
    <div class="mall-hero-inner">
        <div class="hero-chips">
            <span class="border-beam">Escrow MoMo</span>
            <span class="border-beam">KYC người bán</span>
            <span class="border-beam">Relic Seal</span>
        </div>
        <h1>
            Chợ
            <span class="word-rotate" data-word-rotate aria-live="polite">
                <span data-word>iPhone</span>
                <span data-word>Laptop</span>
                <span data-word>Máy ảnh</span>
                <span data-word>Tai nghe</span>
                <span data-word>iPad</span>
                <span data-word>MacBook</span>
            </span>
            cũ — giá tốt, gần bạn
        </h1>
        <p class="lead">Chợ đồ điện tử cũ có người đứng giữa — chọn khu vực để xem tin đúng nơi bạn muốn mua.</p>
        <form class="mall-hero-search mall-search-row" action="{{ route('listings.index') }}" method="get">
            <div class="mall-searchbar">
                <input name="q" value="{{ request('q') }}" placeholder="Tìm iPhone, laptop, máy ảnh..." aria-label="Tìm kiếm">
                @include('partials.glass-search-button')
            </div>
            @include('partials.area-picker', ['pickerName' => 'city'])
        </form>
    </div>
</section>

@if ($marqueeItems->isNotEmpty())
<section class="section blur-fade">
    <div class="section-head">
        <h2>Máy đang nổi bật</h2>
        <a class="btn btn-ghost btn-sm" href="{{ route('listings.index', array_filter(['city' => $currentArea])) }}">Xem chợ</a>
    </div>
    <div class="relic-marquee" style="--marquee-duration: 48s">
        <div class="relic-marquee-track">
            @foreach ([1, 2] as $loopPass)
                @foreach ($marqueeItems as $item)
                    <a class="marquee-card" href="{{ route('listings.show', $item) }}">
                        <img src="{{ $item->coverUrl() }}" alt="{{ $item->title }}" loading="lazy" width="72" height="72">
                        <div>
                            <strong>{{ $item->title }}</strong>
                            <div class="price">{{ $item->formattedPrice() }}</div>
                        </div>
                    </a>
                @endforeach
            @endforeach
        </div>
    </div>
</section>
@endif

@include('partials.category-filmstrip')

@include('listings._promo')

<div class="feed-tabs">
    <a class="is-on" href="#danh-cho-ban">Dành cho bạn</a>
    <a href="#moi-nhat">Mới nhất</a>
    @if ($verified->isNotEmpty())
        <a href="{{ route('listings.index', array_filter(['verified' => 1, 'city' => $currentArea])) }}">Relic Seal</a>
    @endif
</div>

@if ($nearby->isNotEmpty())
<section class="section blur-fade" id="danh-cho-ban">
    <div class="section-head">
        <h2>
            @if ($currentArea)
                Dành cho bạn — {{ $currentArea }}
            @elseif (!empty(session('relic.geo')['lat'] ?? null) || auth()->user()?->lat)
                Gần bạn (GPS 25km)
            @else
                Dành cho bạn{{ $userCity ? ' — '.$userCity : '' }}
            @endif
        </h2>
        <a class="btn btn-ghost btn-sm" href="{{ route('listings.index', array_filter(['city' => $currentArea ?: $userCity])) }}">Xem thêm</a>
    </div>
    <div class="grid">
        @foreach ($nearby as $listing)
            @include('listings._card', ['listing' => $listing])
        @endforeach
    </div>
</section>
@endif

@if ($featured->isNotEmpty())
<section class="section blur-fade">
    <div class="section-head">
        <h2>Quảng cáo</h2>
        <span class="muted">Sản phẩm đăng ký gói marketing</span>
    </div>
    <div class="grid">
        @foreach ($featured as $listing)
            @include('listings._card', ['listing' => $listing])
        @endforeach
    </div>
</section>
@endif

<section class="section blur-fade" id="moi-nhat">
    <div class="section-head">
        <h2>Gợi ý hôm nay</h2>
        <a class="btn btn-ghost btn-sm" href="{{ route('listings.index', array_filter(['city' => $currentArea])) }}">Xem tất cả</a>
    </div>
    <div class="grid">
        @foreach ($suggested as $listing)
            @include('listings._card', ['listing' => $listing])
        @endforeach
        @if ($suggested->isEmpty())
            <p class="empty">Chưa có tin trong khu vực này. Thử chọn Toàn quốc hoặc tỉnh khác.</p>
        @endif
    </div>
</section>

@if ($verified->isNotEmpty())
<section class="section blur-fade">
    <div class="section-head">
        <h2>Relic Seal</h2>
        <a class="btn btn-ghost btn-sm" href="{{ route('listings.index', array_filter(['verified' => 1, 'city' => $currentArea])) }}">Xem tất cả đã đối soát</a>
    </div>
    <div class="grid">
        @foreach ($verified as $listing)
            @include('listings._card', ['listing' => $listing])
        @endforeach
    </div>
</section>
@endif

<section class="section blur-fade" id="lam-gi-tren-relic">
    <div class="section-head">
        <h2>Làm gì trên Relic?</h2>
        <span class="muted">Bento nhanh — chọn một lối đi</span>
    </div>
    <div class="bento">
        <a class="bento-cell tone-blue span-2 span-2-row" href="{{ route('listings.index', array_filter(['city' => $currentArea])) }}">
            <span class="ico">🛒</span>
            <h3>Mua đồ cũ an toàn</h3>
            <p>Lọc theo khu vực, xem ảnh thật, trả MoMo — Relic giữ tiền đến khi bạn nhận máy.</p>
        </a>
        <a class="bento-cell tone-mint" href="{{ route('pages.show', 'how-it-works') }}">
            <span class="ico">🔒</span>
            <h3>Escrow MoMo</h3>
            <p>Tiền không đi thẳng shop. Giữ escrow đến khi xác nhận.</p>
        </a>
        <a class="bento-cell tone-peach" href="{{ route('listings.index', array_filter(['sort' => 'nearby', 'city' => $currentArea])) }}">
            <span class="ico">📍</span>
            <h3>Gần bạn</h3>
            <p>Bật GPS hoặc chọn tỉnh — tin đúng khu vực hiện lên trước.</p>
        </a>
        @unless (auth()->user()?->isAdmin())
        <a class="bento-cell" href="{{ route('seller.listings.index') }}">
            <span class="ico">📦</span>
            <h3>Kênh người bán</h3>
            <p>Quản lý shop, sản phẩm và tài khoản nhận tiền.</p>
        </a>
        @else
        <a class="bento-cell" href="{{ route('listings.index') }}">
            <span class="ico">📦</span>
            <h3>Khám phá chợ</h3>
            <p>Hàng trăm tin điện thoại, laptop, phụ kiện.</p>
        </a>
        <a class="bento-cell" href="{{ route('pages.show', 'how-it-works') }}">
            <span class="ico">📖</span>
            <h3>Cách hoạt động</h3>
            <p>Hiểu nhanh quy trình mua bán trên Relic.</p>
        </a>
        @endunless
        <a class="bento-cell tone-seal" href="{{ route('listings.index', array_filter(['verified' => 1, 'city' => $currentArea])) }}">
            <span class="ico">✅</span>
            <h3>Relic Seal</h3>
            <p>Tin đã đối soát nguồn gốc — yên tâm hơn khi chốt.</p>
        </a>
    </div>
</section>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ url('css/category-filmstrip.css') }}?v=ad2">
@endpush

@push('scripts')
<script src="{{ url('js/relic-magic.js') }}" defer></script>
<script src="{{ url('js/category-filmstrip.js') }}?v=open" defer></script>
@endpush
