@extends('layouts.store')
@section('title', $user->name.' — Shop')
@section('content')
<section class="shop-page">
    <header class="shop-banner">
        <div class="shop-identity">
            <span class="avatar shop-avatar">{{ $user->initials() }}</span>
            <div>
                <h1>{{ $user->name }}</h1>
                <p class="muted">
                    @if ($user->seller_verified) <span class="badge">Shop chuẩn</span> @endif
                    {{ $user->city ?: 'Toàn quốc' }}
                    @if ($user->bio) · {{ $user->bio }} @endif
                </p>
                @if ($chatListing && (! auth()->check() || (auth()->id() !== $user->id && ! auth()->user()->isAdmin())))
                    <a class="btn btn-accent btn-sm" href="{{ route('listings.show', $chatListing) }}#chat-shop">Chat ngay</a>
                @endif
            </div>
        </div>
        <dl class="shop-stats">
            <div><b>{{ $listings->total() }}</b><span>Sản phẩm</span></div>
            <div><b>{{ number_format($rating, 1) }}</b><span>{{ $reviewCount }} đánh giá</span></div>
            <div><b>{{ $soldCount }}</b><span>Đã bán</span></div>
            <div><b>{{ $user->created_at?->format('m/Y') }}</b><span>Tham gia</span></div>
        </dl>
    </header>

    <nav class="shop-tabs">
        <a class="is-on" href="#san-pham">Sản phẩm</a>
        <a href="#danh-gia">Đánh giá ({{ $reviewCount }})</a>
    </nav>

    <section id="san-pham">
        <div class="section-head">
            <h2>Sản phẩm của shop</h2>
            <div class="shop-sort">
                <a class="{{ ($sort ?? 'new') === 'new' ? 'is-on' : '' }}" href="{{ route('shops.show', ['user' => $user, 'sort' => 'new']) }}#san-pham">Mới nhất</a>
                <a class="{{ ($sort ?? '') === 'price_asc' ? 'is-on' : '' }}" href="{{ route('shops.show', ['user' => $user, 'sort' => 'price_asc']) }}#san-pham">Giá tăng</a>
                <a class="{{ ($sort ?? '') === 'price_desc' ? 'is-on' : '' }}" href="{{ route('shops.show', ['user' => $user, 'sort' => 'price_desc']) }}#san-pham">Giá giảm</a>
            </div>
        </div>
        @if ($listings->isEmpty())
            <p class="muted">Shop chưa có sản phẩm đang bán.</p>
        @else
            <div class="grid">
                @foreach ($listings as $listing)
                    @include('listings._card')
                @endforeach
            </div>
            <div class="pager">{{ $listings->links() }}</div>
        @endif
    </section>

    <section class="section" id="danh-gia">
        <h2>Đánh giá shop</h2>
        <div class="rate-box panel">
            <div class="rate-score">
                <b>{{ number_format($rating, 1) }}</b>
                <div class="stars">{{ str_repeat('★', (int) round($rating)) }}{{ str_repeat('☆', 5 - (int) round($rating)) }}</div>
                <p class="muted">{{ $reviewCount }} đánh giá</p>
            </div>
            <div>
                @foreach ($starCounts as $star => $count)
                    <div class="rate-row">
                        <span>{{ $star }} sao</span>
                        <span class="rate-bar"><i style="width: {{ $reviewCount ? round($count / $reviewCount * 100) : 0 }}%"></i></span>
                        <span>{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @forelse ($reviews as $review)
            <div class="panel" style="margin-bottom:8px">
                <strong>{{ $review->reviewer->name ?? 'Người mua' }}</strong>
                · <span class="stars">{{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', 5 - (int) $review->rating) }}</span>
                <span class="muted">{{ $review->created_at->format('d/m/Y') }}</span>
                @if ($review->listing)
                    <span class="muted">· {{ $review->listing->title }}</span>
                @endif
                <p>{{ $review->comment ?: 'Không có nội dung.' }}</p>
            </div>
        @empty
            <p class="muted">Shop chưa có đánh giá.</p>
        @endforelse
    </section>
</section>
@endsection
