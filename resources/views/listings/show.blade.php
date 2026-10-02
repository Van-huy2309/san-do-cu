@extends('layouts.store')
@section('title', $listing->title)
@section('content')
<div class="detail">
    <div class="gallery">
        <div class="gallery-main thumb-3d">
            <img src="{{ $listing->coverUrl() }}" alt="{{ $listing->title }}">
        </div>
        <div class="meta">
            @foreach ($listing->images as $img)
                <img src="{{ $img->url() }}" alt="" width="80" height="60" loading="lazy" style="border-radius:8px;object-fit:cover">
            @endforeach
        </div>
    </div>
    <div class="panel">
        @if ($listing->isOriginVerified())
            <div class="seal">
                <strong>Relic Seal {{ $listing->origin->seal_code }}</strong>
                <p class="muted">Nguồn gốc đã đối soát. Serial ****{{ $listing->origin->serial_last4 }} @if($listing->origin->imei_last4) · IMEI ****{{ $listing->origin->imei_last4 }} @endif</p>
            </div>
        @else
            <span class="badge badge-wait">{{ $listing->origin?->statusLabel() ?? 'Chưa gửi hồ sơ nguồn gốc' }}</span>
        @endif
        <h1>{{ $listing->title }}</h1>
        <div class="price" style="font-size:1.6rem">{{ $listing->formattedPrice() }}</div>
        <p class="meta">
            <span>{{ $listing->category->name ?? '—' }}</span>
            <span>{{ $listing->brand->name ?? '—' }}</span>
            <span>{{ $listing->conditionLabel() }}</span>
            <span>{{ $listing->areasLabel() }}</span>
            <span>{{ $listing->views }} lượt xem</span>
        </p>
        <ul class="muted">
            <li>Hộp: {{ $listing->extra('has_box') ? 'Còn hộp' : 'Không hộp' }}</li>
            <li>Bảo hành: {{ $listing->extra('has_warranty') ? ($listing->extra('warranty_months') ?: '?') . ' tháng' : 'Hết / không BH' }}</li>
            @if ($listing->extra('scratch_note'))
                <li>Trầy xước / thiếu: {{ $listing->extra('scratch_note') }}</li>
            @endif
        </ul>
        <p>{{ nl2br(e($listing->description)) }}</p>
        <ul class="muted">
            <li>Model: {{ $listing->model ?: '—' }}</li>
            <li>Màu: {{ $listing->color ?: '—' }}</li>
            <li>Dung lượng: {{ $listing->storage_gb ? $listing->storage_gb.' GB' : '—' }}</li>
            <li>Năm: {{ $listing->year_released ?: '—' }}</li>
            @if ((int) $listing->original_price > 0 && (int) $listing->original_price !== (int) $listing->price)
                <li>Giá niêm yết hãng: {{ number_format($listing->original_price, 0, ',', '.') }} ₫</li>
            @endif
        </ul>
        @if ($listing->status === 'sold')
            <p><span class="badge badge-wait">Đã bán — vẫn xem được chi tiết & đánh giá</span></p>
        @endif
        <p>Người bán</p>
        <a class="shop-strip" href="{{ route('shops.show', $listing->seller) }}">
            <span class="avatar">{{ $listing->seller->initials() }}</span>
            <span>
                <strong>{{ $listing->seller->name }}</strong>
                @if ($listing->seller->seller_verified) <span class="badge">Shop chuẩn</span> @endif
                <span class="muted">{{ number_format($shopAvg, 1) }}/5 · {{ $shopCount }} đánh giá · {{ $shopListingCount }} sản phẩm</span>
            </span>
            <span class="btn btn-ghost btn-sm">Xem shop</span>
        </a>

        @if ($listing->isActive() && (! auth()->check() || ($listing->seller_id !== auth()->id() && ! auth()->user()->isAdmin())))
            <div class="card-actions" style="margin:16px 0">
                <form method="post" action="{{ route('user.cart.add', $listing) }}">
                    @csrf
                    <input type="hidden" name="buy_now" value="1">
                    <button class="btn btn-accent" type="submit">Mua ngay</button>
                </form>
                <form method="post" action="{{ route('user.cart.add', $listing) }}">
                    @csrf
                    <button class="btn btn-ghost" type="submit">Thêm vào giỏ</button>
                </form>
                @auth
                    <form method="post" action="{{ route('favorites.toggle', $listing) }}">
                        @csrf
                        <button class="btn btn-ghost" type="submit">{{ in_array($listing->id, $favoriteIds ?? []) ? 'Bỏ yêu thích' : 'Lưu yêu thích' }}</button>
                    </form>
                @endauth
            </div>
        @endif

        @auth
            @if ($listing->isActive() && $listing->seller_id !== auth()->id() && ! auth()->user()->isAdmin())
                <form method="post" action="{{ route('listings.report', $listing) }}" style="margin-top:12px">
                    @csrf
                    <select class="field" name="reason">
                        <option value="fake">Hàng giả / không đúng mô tả</option>
                        <option value="wrong_price">Giá bất thường</option>
                        <option value="scam">Nghi lừa đảo</option>
                        <option value="other">Khác</option>
                    </select>
                    <button class="btn btn-ghost btn-sm" type="submit">Báo cáo tin</button>
                </form>
            @endif
        @endauth
    </div>
</div>

@include('listings._shop_chat')

<section class="section" id="danh-gia">
    <h2>Đánh giá shop</h2>
    <div class="panel rate-box" style="margin-bottom:16px">
        <div class="rate-score">
            <b>{{ $shopCount ? number_format($shopAvg, 1) : '—' }}</b>
            @php $filled = $shopCount ? max(0, min(5, (int) round($shopAvg))) : 0; @endphp
            <div class="stars">{{ str_repeat('★', $filled) }}{{ str_repeat('☆', 5 - $filled) }}</div>
            <p class="muted">{{ $shopCount }} đánh giá</p>
        </div>
        <div>
            @foreach ($breakdown as $star => $n)
                @php $pct = $shopCount ? round($n / $shopCount * 100) : 0; @endphp
                <div class="rate-row">
                    <span>{{ $star }} sao</span>
                    <div class="rate-bar"><i style="width: {{ $pct }}%"></i></div>
                    <span>{{ $n }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @auth
        @if ($canReview)
            <form class="panel" method="post" action="{{ route('reviews.store', $listing) }}" style="margin-bottom:16px">
                @csrf
                <h3>{{ $myReview ? 'Sửa đánh giá của bạn' : 'Đánh giá sau khi mua' }}</h3>
                <div class="star-pick">
                    @for ($i = 5; $i >= 1; $i--)
                        <label>
                            <input type="radio" name="rating" value="{{ $i }}" @checked(old('rating', $myReview->rating ?? 5) == $i) required>
                            <span>{{ str_repeat('★', $i) }}</span>
                        </label>
                    @endfor
                </div>
                <textarea class="field" name="comment" rows="3" placeholder="Máy đúng mô tả? Shop hỗ trợ thế nào?">{{ old('comment', $myReview->comment ?? '') }}</textarea>
                <button class="btn btn-accent" type="submit">Gửi đánh giá</button>
            </form>
        @elseif (auth()->id() !== $listing->seller_id)
            <p class="muted">Chỉ người đã mua tin này mới được đánh giá sao và bình luận.</p>
        @endif
    @else
        <p class="muted"><a href="{{ route('login') }}">Đăng nhập</a> để đánh giá nếu bạn đã mua hàng.</p>
    @endauth

    <h3>Bình luận</h3>
    @forelse ($shopReviews as $review)
        <div class="panel" style="margin-bottom:8px">
            <strong>{{ $review->reviewer->name }}</strong>
            · <span class="stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
            <span class="muted">{{ $review->created_at->format('d/m/Y') }}</span>
            @if ($review->listing_id === $listing->id)
                <span class="badge">Tin này</span>
            @endif
            <p>{{ $review->comment ?: 'Không có nội dung.' }}</p>
        </div>
    @empty
        <p class="muted">Shop chưa có đánh giá.</p>
    @endforelse
</section>

<section class="section">
    <h2>Tin cùng danh mục</h2>
    <div class="grid">
        @foreach ($related as $item)
            @include('listings._card', ['listing' => $item])
        @endforeach
    </div>
</section>
@endsection
