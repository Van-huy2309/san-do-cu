<article class="card magic-card">
    <div class="card-media-wrap">
        <a href="{{ route('listings.show', $listing) }}" class="card-media thumb-3d">
            <img src="{{ $listing->coverUrl() }}" alt="{{ $listing->title }}" loading="lazy" width="400" height="300">
        </a>
        @auth
            <form method="post" action="{{ route('favorites.toggle', $listing) }}" class="fav-form">
                @csrf
                <button type="submit" class="fav-btn {{ in_array($listing->id, $favoriteIds ?? []) ? 'is-on' : '' }}" aria-label="Yêu thích">
                    {{ in_array($listing->id, $favoriteIds ?? []) ? '♥' : '♡' }}
                </button>
            </form>
        @endauth
        @if ($listing->isBoosted())
            <span class="card-flag">Đẩy tin</span>
        @endif
    </div>
    <div class="card-body">
        @if ($listing->isOriginVerified())
            <span class="badge">Relic Seal</span>
        @endif
        <h3><a href="{{ route('listings.show', $listing) }}">{{ $listing->title }}</a></h3>
        <div class="price">{{ $listing->formattedPrice() }}</div>
        @if ($listing->seller)
            <a class="card-shop" href="{{ route('shops.show', $listing->seller) }}">
                <span class="avatar">{{ $listing->seller->initials() }}</span>
                <span>{{ $listing->seller->name }}</span>
            </a>
        @endif
        <div class="meta">
            <span>{{ $listing->conditionLabel() }}</span>
            <span>{{ $listing->areasLabel() }}</span>
            <span>{{ $listing->brand->name ?? '' }}</span>
            @if ($listing->distance_km)
                <span>{{ $listing->distance_km }} km</span>
            @endif
        </div>
        @if ($listing->isActive())
        <div class="card-actions">
            @unless (auth()->user()?->isAdmin())
            <form method="post" action="{{ route('user.cart.add', $listing) }}">
                @csrf
                <input type="hidden" name="buy_now" value="1">
                <button class="btn btn-accent btn-sm" type="submit">Mua ngay</button>
            </form>
            <form method="post" action="{{ route('user.cart.add', $listing) }}">
                @csrf
                <button class="btn btn-cart btn-sm" type="submit">Thêm giỏ</button>
            </form>
            @endunless
        </div>
        @else
            <p class="muted">Đã bán</p>
        @endif
    </div>
</article>
