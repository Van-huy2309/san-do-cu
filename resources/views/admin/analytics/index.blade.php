@extends('layouts.admin')
@section('title', 'Doanh thu')
@section('content')
@include('admin.partials.chartjs')
<h1>Báo cáo doanh thu</h1>
<p class="muted">Hoa hồng 5% · khoảng {{ $days }} ngày gần nhất ({{ $summary['from']->format('d/m/Y') }} – {{ $summary['to']->format('d/m/Y') }}). Đánh giá doanh số: hỏi Relic Ops.</p>

<div class="toolbar" style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 8px;align-items:center">
    @foreach ([7, 14, 30, 90] as $d)
        <a class="btn btn-sm {{ $days === $d ? 'btn-accent' : 'btn-ghost' }}" href="{{ route('admin.analytics', ['days' => $d]) }}">{{ $d }} ngày</a>
    @endforeach
    <a class="btn btn-accent btn-sm" href="{{ route('admin.analytics.export', ['kind' => 'revenue', 'days' => $days]) }}">Xuất Excel doanh thu</a>
    <a class="btn btn-ghost btn-sm" href="{{ route('admin.analytics.export', ['kind' => 'products', 'days' => $days]) }}">Xuất Excel sản phẩm</a>
    <a class="btn btn-ghost btn-sm" href="{{ route('admin.analytics.export', ['kind' => 'orders', 'days' => $days]) }}">Xuất Excel đơn</a>
</div>

<div class="stats">
    <div class="stat"><b>{{ number_format($summary['revenue']) }}₫</b><span>Doanh thu sàn</span></div>
    <div class="stat"><b>{{ number_format($summary['commission']) }}₫</b><span>Hoa hồng 5%</span></div>
    <div class="stat"><b>{{ number_format($summary['gmv_released']) }}₫</b><span>GMV đã giải ngân</span></div>
    <div class="stat"><b>{{ number_format($summary['gmv_held']) }}₫</b><span>Escrow đang giữ</span></div>
    <div class="stat"><b>{{ $summary['orders'] }}</b><span>Đơn trong kỳ</span></div>
    <div class="stat"><b>{{ $summary['new_users'] }}</b><span>User mới</span></div>
    <div class="stat"><b>{{ $summary['listings_active'] }}</b><span>Tin đang bán</span></div>
</div>

<div class="panel" style="margin-top:16px">
    <h3>Dự tính doanh thu tới (nền {{ $forecast['basis_days'] }} ngày · TB {{ number_format($forecast['avg_daily']) }}₫/ngày · xu hướng {{ $forecast['trend_pct'] >= 0 ? '+' : '' }}{{ $forecast['trend_pct'] }}%)</h3>
    <div class="stats" style="margin-top:12px">
        @foreach ($forecast['horizons'] as $h)
            <div class="stat">
                <b>{{ number_format($h['mid']) }}₫</b>
                <span>{{ $h['days'] }} ngày tới · {{ number_format($h['low']) }}–{{ number_format($h['high']) }}₫ · ~{{ $h['orders_mid'] }} đơn</span>
            </div>
        @endforeach
    </div>
    <p class="muted" style="margin-top:8px">Ước lượng tham khảo. Muốn đánh giá doanh số chi tiết → hỏi Ops “đánh giá doanh số”.</p>
</div>

<div class="detail analytics-charts" style="margin-top:20px">
    <div class="panel chart-panel">
        <h3>Doanh thu theo ngày</h3>
        <div class="chart-box"><canvas id="chartRevenue"></canvas></div>
    </div>
    <div class="panel chart-panel">
        <h3>Đơn theo ngày</h3>
        <div class="chart-box"><canvas id="chartOrders"></canvas></div>
    </div>
</div>

<div class="detail analytics-pies" style="margin-top:12px">
    <div class="panel chart-panel">
        <h3>Đơn theo trạng thái</h3>
        <div class="chart-box chart-box-pie"><canvas id="chartOrderStatus"></canvas></div>
    </div>
    <div class="panel chart-panel">
        <h3>Tin theo trạng thái</h3>
        <div class="chart-box chart-box-pie"><canvas id="chartListingStatus"></canvas></div>
    </div>
    <div class="panel chart-panel">
        <h3>User theo KYC</h3>
        <div class="chart-box chart-box-pie"><canvas id="chartKyc"></canvas></div>
    </div>
</div>

<div class="panel" style="margin-top:16px">
    <h3>Top sản phẩm (theo doanh thu item)</h3>
    @forelse ($topProducts as $p)
        <p>{{ $p->title }} · SL {{ $p->qty }} · {{ number_format((int) $p->revenue) }}₫</p>
    @empty
        <p class="muted">Chưa có đơn trong kỳ.</p>
    @endforelse
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const series = @json($series);
    const orderStatus = @json($ordersByStatus);
    const listingStatus = @json($listingsByStatus);
    const kyc = @json($usersByKyc);
    const palette = ['#1d6ef5', '#0ea5a0', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b'];
    const baseOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
    };

    new Chart(document.getElementById('chartRevenue'), {
        type: 'line',
        data: {
            labels: series.labels,
            datasets: [
                { label: 'Hoa hồng', data: series.commission, borderColor: '#1d6ef5', tension: .3, fill: false, pointRadius: 2 },
            ]
        },
        options: { ...baseOpts, scales: { y: { beginAtZero: true, ticks: { font: { size: 10 } } }, x: { ticks: { font: { size: 10 }, maxTicksLimit: 8 } } } }
    });
    new Chart(document.getElementById('chartOrders'), {
        type: 'bar',
        data: { labels: series.labels, datasets: [{ label: 'Đơn', data: series.orders, backgroundColor: '#1d6ef5' }] },
        options: { ...baseOpts, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } }, x: { ticks: { font: { size: 10 }, maxTicksLimit: 8 } } } }
    });
    const pie = (id, map) => {
        const labels = Object.keys(map);
        const data = Object.values(map);
        if (!labels.length) return;
        new Chart(document.getElementById(id), {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: palette.slice(0, labels.length) }] },
            options: baseOpts
        });
    };
    pie('chartOrderStatus', orderStatus);
    pie('chartListingStatus', listingStatus);
    pie('chartKyc', kyc);
});
</script>
@endsection
