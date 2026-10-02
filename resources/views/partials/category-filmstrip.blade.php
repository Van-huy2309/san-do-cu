@php
    $filmstripAds = collect($filmstripAds ?? []);
    $first = $filmstripAds->first();
@endphp
@if ($filmstripAds->isNotEmpty())
<section class="section blur-fade category-filmstrip-section" id="noi-bat">
    <div class="section-head">
        <h2>Được tìm nhiều</h2>
        <span class="category-filmstrip-ad-badge">Quảng cáo</span>
    </div>

    <div class="threeui-background character-carousel character-carousel--filmstrip category-filmstrip-host">
        <main class="stage" id="category-filmstrip-stage" aria-label="Băng quảng cáo sản phẩm được tìm nhiều">
            <h3 class="sr-only">Sản phẩm được tìm nhiều trên Relic</h3>
            <p class="sr-only">Nhấn hoặc kéo để xem. Nhấn đúp để đổi ngang/dọc. Bấm Xem tin để mở sản phẩm.</p>
            <div class="deck" id="category-filmstrip-deck" data-testid="filmstrip">
                @foreach ($filmstripAds as $index => $ad)
                    <div
                        class="card"
                        role="img"
                        data-index="{{ $index }}"
                        data-name="{{ $ad['name'] }}"
                        data-icon="{{ $ad['icon'] }}"
                        data-price="{{ $ad['price'] }}"
                        data-url="{{ $ad['url'] ?? '' }}"
                        aria-label="{{ $ad['name'] }}, {{ $ad['price'] }}"
                    >
                        <span class="card-ad-stamp" aria-hidden="true">QC</span>
                        <span class="portrait">
                            @if (!empty($ad['image']))
                                <img src="{{ $ad['image'] }}" alt="" loading="lazy" decoding="async" draggable="false" width="220" height="280">
                            @endif
                            <span class="portrait-icon" aria-hidden="true">{{ $ad['icon'] }}</span>
                        </span>
                        <span class="footer">
                            <span class="index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="meta">
                                <span class="name">{{ $ad['name'] }}</span>
                                <span class="role">{{ $ad['price'] }}</span>
                            </span>
                        </span>
                    </div>
                @endforeach
            </div>
        </main>
    </div>

    <aside class="category-filmstrip-picked" id="category-filmstrip-picked" hidden>
        <span class="picked-icon" data-picked-icon>{{ $first['icon'] ?? '' }}</span>
        <div class="picked-copy">
            <p class="picked-kicker">Đang xem · Quảng cáo</p>
            <strong data-picked-name>{{ $first['name'] ?? '' }}</strong>
            <p class="muted" data-picked-desc>{{ $first['price'] ?? '' }}</p>
            <a class="btn btn-accent btn-sm" data-picked-link href="{{ $first['url'] ?? route('listings.index') }}">Xem tin</a>
        </div>
    </aside>
</section>
@endif
