@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
<h1>Vận hành Relic</h1>
<p class="muted">Số liệu tổng hợp · <a href="{{ route('admin.analytics') }}">Báo cáo doanh thu & Excel</a></p>

<div class="stats">
    @foreach ($stats as $k => $v)
        <div class="stat"><b>{{ $v }}</b><span>{{ $k }}</span></div>
    @endforeach
</div>

<div class="stats" style="margin-top:12px">
    <div class="stat"><b>{{ number_format($summary['revenue']) }}₫</b><span>Doanh thu 30 ngày</span></div>
    <div class="stat"><b>{{ number_format($summary['commission']) }}₫</b><span>Hoa hồng 5%</span></div>
    <div class="stat"><b>{{ number_format($summary['boost']) }}₫</b><span>Phí đẩy tin</span></div>
    <div class="stat"><b>{{ number_format($summary['gmv_held']) }}₫</b><span>Escrow đang giữ</span></div>
</div>

<div class="detail" style="margin-top:24px">
    <div class="panel">
        <h3>Tin mới trên chợ</h3>
        @forelse ($pendingListings as $l)
            <p>{{ $l->title }} · {{ $l->seller->name }}</p>
        @empty
            <p class="muted">Không có tin chờ duyệt.</p>
        @endforelse
        <a href="{{ route('admin.listings') }}">Xem tất cả</a>
    </div>
    <div class="panel">
        <h3>Đơn mới</h3>
        @foreach ($latestOrders as $o)
            <p>{{ $o->code }} · {{ $o->status }} · {{ number_format($o->total_price) }}</p>
        @endforeach
        <a href="{{ route('admin.orders') }}">Xem đơn</a>
    </div>
</div>
@endsection
