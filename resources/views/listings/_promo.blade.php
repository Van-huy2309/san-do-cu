@if (isset($promoListings) && $promoListings->isNotEmpty())
<section class="promo" id="promo" aria-roledescription="carousel" aria-label="Quảng cáo gợi ý">
    <div class="promo-view">
        <div class="promo-track" id="promo-track">
            @foreach ($promoListings as $promo)
                <article class="promo-slide">
                    <div class="promo-copy">
                        <span class="promo-tag">
                            @if (! empty($promoTerms) && $promoTerms->isNotEmpty())
                                Vì bạn hay tìm “{{ $promoTerms->first() }}”
                            @else
                                Đang được săn nhiều
                            @endif
                        </span>
                        <h2>{{ $promo->title }}</h2>
                        <p class="promo-price">{{ $promo->formattedPrice() }}</p>
                        <p class="muted">{{ $promo->conditionLabel() }} · {{ $promo->areasLabel() }} · {{ $promo->views }} lượt xem</p>
                        <a class="btn btn-accent" href="{{ route('listings.show', $promo) }}">Xem ngay</a>
                    </div>
                    <div class="promo-art">
                        <div class="promo-cube">
                            <img src="{{ $promo->coverUrl() }}" alt="{{ $promo->title }}" loading="lazy">
                            <span class="promo-shine"></span>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
    <button type="button" class="promo-arrow promo-prev" data-dir="-1" aria-label="Quảng cáo trước">‹</button>
    <button type="button" class="promo-arrow promo-next" data-dir="1" aria-label="Quảng cáo sau">›</button>
    <div class="promo-dots" id="promo-dots">
        @foreach ($promoListings as $i => $promo)
            <button type="button" class="promo-dot {{ $i === 0 ? 'is-on' : '' }}" data-go="{{ $i }}" aria-label="Quảng cáo {{ $i + 1 }}"></button>
        @endforeach
    </div>
</section>

@push('scripts')
<script>
(function () {
    const track = document.getElementById('promo-track');
    if (!track) return;
    const slides = track.children.length;
    const dots = Array.from(document.querySelectorAll('.promo-dot'));
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let i = 0, timer = null;

    function go(next) {
        i = (next + slides) % slides;
        track.style.transform = 'translateX(' + (-i * 100) + '%)';
        dots.forEach((d, n) => d.classList.toggle('is-on', n === i));
    }
    function play() { if (!reduce) { stop(); timer = setInterval(() => go(i + 1), 3000); } }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }

    document.querySelectorAll('.promo-arrow').forEach(btn => {
        btn.addEventListener('click', () => { go(i + Number(btn.dataset.dir)); play(); });
    });
    dots.forEach(d => d.addEventListener('click', () => { go(Number(d.dataset.go)); play(); }));

    const box = document.getElementById('promo');
    box.addEventListener('mouseenter', stop);
    box.addEventListener('mouseleave', play);
    document.addEventListener('visibilitychange', () => document.hidden ? stop() : play());

    if (!reduce) {
        box.addEventListener('pointermove', (e) => {
            const r = box.getBoundingClientRect();
            const x = (e.clientX - r.left) / r.width - .5;
            const y = (e.clientY - r.top) / r.height - .5;
            box.style.setProperty('--tilt-y', (x * 16).toFixed(2) + 'deg');
            box.style.setProperty('--tilt-x', (-y * 12).toFixed(2) + 'deg');
        });
        box.addEventListener('pointerleave', () => {
            box.style.removeProperty('--tilt-y');
            box.style.removeProperty('--tilt-x');
        });
    }

    go(0);
    play();
})();
</script>
@endpush
@endif
