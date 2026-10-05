@extends('layouts.seller')
@section('title', 'Tin đang bán')
@section('content')
<div class="meta" style="justify-content:space-between;align-items:center">
    <h1>Kênh người bán</h1>
    <a class="btn btn-accent" href="{{ route('seller.listings.create') }}">Đăng tin mới</a>
</div>
<p class="muted">Đã bán: {{ $sales }} máy</p>
@if ($bank)
    <p class="muted">Tiền về: {{ $bank->masked() }}</p>
@else
    <div class="flash flash-warn">Shop chưa có tài khoản ngân hàng. <a href="{{ route('seller.bank') }}">Đăng ký để nhận tiền sau phí sàn.</a></div>
@endif
@php($slotsOpen = $packages->contains(fn ($package) => $package->remainingSlots() > 0))
<div class="panel" style="margin:12px 0 16px">
    <h2>Gói quảng cáo</h2>
    @if ($packages->isEmpty())
        <p class="muted">Admin chưa phát hành gói nào. Nút đăng ký ở từng tin sẽ tối cho đến khi có suất.</p>
    @else
        <table class="table">
            <tr><th>Gói</th><th>Suất còn</th><th>Thời hạn</th></tr>
            @foreach ($packages as $package)
                <tr>
                    <td>
                        <strong>{{ $package->name }}</strong>
                        @if ($package->description)<div class="muted">{{ $package->description }}</div>@endif
                    </td>
                    <td>{{ $package->remainingSlots() }} / {{ $package->slot_limit }}</td>
                    <td>{{ $package->duration_days }} ngày</td>
                </tr>
            @endforeach
        </table>
    @endif
</div>
@foreach ($listings as $listing)
    <div class="panel" style="margin-bottom:10px">
        <strong>{{ $listing->title }}</strong>
        <span class="badge">{{ $listing->statusLabel() }}</span>
        @if ($ad = $listing->marketingEnrollments->first())
            <span class="badge">Quảng cáo đến {{ $ad->ends_at->format('d/m') }}</span>
        @endif
        @if ($listing->origin)<span class="badge">{{ $listing->origin->statusLabel() }}</span>@endif
        <p>{{ $listing->formattedPrice() }} · {{ $listing->category->name }}</p>
        <div class="card-actions">
            @if ($listing->status !== 'sold')
                <a class="btn btn-ghost btn-sm" href="{{ route('seller.listings.edit', $listing) }}">Sửa</a>
            @endif
            @if ($listing->isActive())
                <form method="post" action="{{ route('seller.listings.hide', $listing) }}">@csrf<button class="btn btn-ghost btn-sm">Ẩn</button></form>
                <form method="post" action="{{ route('seller.listings.sold', $listing) }}" onsubmit="return confirm('Đánh dấu đã bán?')">@csrf<button class="btn btn-ghost btn-sm">Đã bán</button></form>
                <form method="post" action="{{ route('seller.listings.promote', $listing) }}" class="meta" style="gap:6px;{{ $slotsOpen ? '' : 'opacity:.4;filter:grayscale(.8)' }}">
                    @csrf
                    <select class="field" name="marketing_package_id" @disabled(! $slotsOpen) @required($slotsOpen)>
                        @forelse ($packages as $package)
                            @if ($slotsOpen && $package->remainingSlots() < 1)
                                @continue
                            @endif
                            <option value="{{ $package->id }}" @disabled($package->remainingSlots() < 1)>
                                {{ $package->name }} — còn {{ $package->remainingSlots() }}/{{ $package->slot_limit }}
                            </option>
                        @empty
                            <option value="">Chưa có gói</option>
                        @endforelse
                    </select>
                    <button class="btn btn-accent btn-sm" type="submit" @disabled(! $slotsOpen)>Đăng ký quảng cáo</button>
                </form>
            @elseif ($listing->status === 'hidden')
                <form method="post" action="{{ route('seller.listings.publish', $listing) }}">@csrf<button class="btn btn-ghost btn-sm">Hiện lại</button></form>
                <form method="post" action="{{ route('seller.listings.sold', $listing) }}">@csrf<button class="btn btn-ghost btn-sm">Đã bán</button></form>
            @endif
        </div>
    </div>
@endforeach
<div class="pager">{{ $listings->links() }}</div>
@endsection
