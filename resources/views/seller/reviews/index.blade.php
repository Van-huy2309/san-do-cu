@extends('layouts.seller')
@section('title', 'Phản hồi')
@section('content')
<h1>Phản hồi người mua</h1>
<p class="muted">Trả lời đánh giá về sản phẩm của shop. Câu trả lời hiện ngay dưới bình luận trên trang tin.</p>
@forelse ($reviews as $review)
    <div class="panel" style="margin-bottom:12px">
        <strong>{{ $review->reviewer->name ?? 'Người mua' }}</strong>
        · <span class="stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
        <span class="muted">{{ $review->created_at->format('d/m/Y H:i') }}</span>
        <p>
            @if ($review->listing)
                <a href="{{ route('listings.show', $review->listing) }}">{{ $review->listing->title }}</a>
            @else
                Sản phẩm đã gỡ
            @endif
        </p>
        <p>{{ $review->comment ?: 'Không có nội dung.' }}</p>
        <form method="post" action="{{ route('seller.reviews.reply', $review) }}">
            @csrf
            <label>Trả lời của shop</label>
            <textarea class="field" name="seller_reply" rows="3" maxlength="500" required placeholder="Thông tin pin, phụ kiện, bảo hành...">{{ old('seller_reply', $review->seller_reply) }}</textarea>
            @if ($review->replied_at)
                <p class="muted">Đã trả lời {{ $review->replied_at->format('d/m/Y H:i') }}. Gửi lại để cập nhật.</p>
            @endif
            <button class="btn btn-accent btn-sm" type="submit">{{ $review->seller_reply ? 'Cập nhật phản hồi' : 'Gửi phản hồi' }}</button>
        </form>
    </div>
@empty
    <p class="muted">Chưa có đánh giá nào. Phản hồi xuất hiện sau khi người mua đã mua và gửi sao.</p>
@endforelse
<div class="pager">{{ $reviews->links() }}</div>
@endsection
