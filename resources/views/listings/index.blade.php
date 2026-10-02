@extends('layouts.store')
@section('title', $search !== '' ? 'Tìm: ' . $search : 'Chợ Relic')
@section('content')
@include('listings._promo')
<div class="mall-filters">
    <form method="get" class="mall-filters-form">
        <input class="field" name="q" value="{{ request('q') }}" placeholder="Tìm tên máy, hãng, danh mục hoặc tên shop">
        <select class="field" name="category">
            <option value="">Danh mục</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <select class="field" name="brand">
            <option value="">Hãng</option>
            @foreach ($brands as $b)
                <option value="{{ $b->id }}" @selected(request('brand') == $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
        <select class="field" name="city">
            <option value="">Toàn quốc</option>
            @foreach ($cities as $city)
                <option value="{{ $city }}" @selected(($currentArea ?? request('city')) == $city)>{{ $city }}</option>
            @endforeach
        </select>
        <select class="field" name="sort">
            <option value="newest" @selected($sort==='newest')>Liên quan / mới nhất</option>
            <option value="nearby" @selected($sort==='nearby')>Gần tôi (tỉnh hồ sơ)</option>
            <option value="price_asc" @selected($sort==='price_asc')>Giá tăng</option>
            <option value="price_desc" @selected($sort==='price_desc')>Giá giảm</option>
            <option value="popular" @selected($sort==='popular')>Xem nhiều</option>
        </select>
        <select class="field" name="condition">
            <option value="">Tình trạng</option>
            @foreach (\App\Models\Listing::CONDITIONS as $k => $label)
                <option value="{{ $k }}" @selected(request('condition') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <input class="field" type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Giá từ">
        <input class="field" type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Giá đến">
        <label class="field" style="display:flex;align-items:center;gap:8px;min-width:auto;margin:0">
            <input type="checkbox" name="verified" value="1" @checked(request()->boolean('verified'))> Relic Seal
        </label>
        <input type="hidden" name="lat" data-geo-lat value="{{ request('lat') }}">
        <input type="hidden" name="lng" data-geo-lng value="{{ request('lng') }}">
        <input class="field" type="number" name="radius" min="1" max="200" value="{{ request('radius', 25) }}" placeholder="Bán kính km">
        <button class="btn btn-ghost" type="button" data-geo>GPS gần tôi</button>
        <input type="hidden" name="gps" value="1">
        <button class="btn btn-accent" type="submit">Lọc</button>
    </form>
</div>

@if ($search !== '')
    <p class="muted">Kết quả cho “{{ $search }}”: {{ $listings->total() }} tin
        @if ($shops->isNotEmpty()) · {{ $shops->count() }} shop @endif
    </p>
@endif

@if ($shops->isNotEmpty())
    <section class="shop-hits">
        <h2>Shop phù hợp</h2>
        <div class="shop-hit-grid">
            @foreach ($shops as $shop)
                <a class="shop-hit" href="{{ route('shops.show', $shop) }}">
                    <span class="avatar">{{ $shop->initials() }}</span>
                    <div>
                        <strong>{{ $shop->name }}</strong>
                        @if ($shop->seller_verified) <span class="badge">Shop chuẩn</span> @endif
                        <div class="muted" style="font-size:.8rem">
                            <span class="stars">{{ str_repeat('★', max(0, min(5, (int) round($shop->ratingScore())))) }}</span>
                            {{ number_format($shop->ratingScore(), 1) }}/5 · {{ $shop->listings_count }} tin đang bán
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif

<div class="grid">
    @forelse ($listings as $listing)
        @include('listings._card')
    @empty
        <p class="empty">Không tìm thấy tin nào khớp. Thử bớt từ khóa hoặc bỏ bộ lọc.</p>
    @endforelse
</div>
{{ $listings->links() }}
@endsection
