@extends('layouts.account')
@section('title', 'Tin đang bán')
@section('content')
<div class="meta" style="justify-content:space-between;align-items:center">
    <h1>Kênh người bán</h1>
    <a class="btn btn-accent" href="{{ route('seller.listings.create') }}">Đăng tin mới</a>
</div>
<p class="muted">Đã bán: {{ $sales }} máy · Đẩy tin: {{ number_format(\App\Services\WalletService::BOOST_FEE) }} ₫ / 7 ngày</p>
@foreach ($listings as $listing)
    <div class="panel" style="margin-bottom:10px">
        <strong>{{ $listing->title }}</strong>
        <span class="badge">{{ $listing->statusLabel() }}</span>
        @if ($listing->isBoosted()) <span class="badge">Đẩy đến {{ $listing->featured_until?->format('d/m') }}</span> @endif
        @if ($listing->origin)<span class="badge">{{ $listing->origin->statusLabel() }}</span>@endif
        <p>{{ $listing->formattedPrice() }} · {{ $listing->category->name }}</p>
        <div class="card-actions">
            @if ($listing->status !== 'sold')
                <a class="btn btn-ghost btn-sm" href="{{ route('seller.listings.edit', $listing) }}">Sửa</a>
            @endif
            @if ($listing->isActive())
                <form method="post" action="{{ route('seller.listings.hide', $listing) }}">@csrf<button class="btn btn-ghost btn-sm">Ẩn</button></form>
                <form method="post" action="{{ route('seller.listings.sold', $listing) }}" onsubmit="return confirm('Đánh dấu đã bán?')">@csrf<button class="btn btn-ghost btn-sm">Đã bán</button></form>
                <form method="post" action="{{ route('seller.listings.boost', $listing) }}" onsubmit="return confirm('Trừ ví {{ number_format(\App\Services\WalletService::BOOST_FEE) }} ₫ để đẩy 7 ngày?')">@csrf<button class="btn btn-accent btn-sm">Đẩy tin</button></form>
            @elseif ($listing->status === 'hidden')
                <form method="post" action="{{ route('seller.listings.publish', $listing) }}">@csrf<button class="btn btn-ghost btn-sm">Hiện lại</button></form>
                <form method="post" action="{{ route('seller.listings.sold', $listing) }}">@csrf<button class="btn btn-ghost btn-sm">Đã bán</button></form>
            @endif
        </div>
    </div>
@endforeach
<div class="pager">{{ $listings->links() }}</div>
@endsection
