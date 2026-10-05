@extends('layouts.admin')
@section('title', 'Tài chính')
@section('content')
@include('admin.partials.chartjs')
<h1>Đối soát ví & escrow</h1>
<p class="muted"><a href="{{ route('admin.finance') }}">Thống kê thanh toán</a> ·
    <a href="{{ route('admin.analytics') }}">Xem biểu đồ doanh thu</a> ·
    <a href="{{ route('admin.analytics.export', ['kind' => 'revenue', 'days' => 30]) }}">Xuất Excel doanh thu</a></p>
<div class="stats">
    <div class="stat"><b>{{ number_format($held) }} ₫</b><span>Escrow đang giữ</span></div>
    <div class="stat"><b>{{ number_format($released) }} ₫</b><span>Đã giải ngân (gốc)</span></div>
    <div class="stat"><b>{{ number_format($commission) }} ₫</b><span>Phí sàn 5%</span></div>
    <div class="stat"><b>{{ number_format($summary['revenue']) }} ₫</b><span>Doanh thu 30 ngày</span></div>
    <div class="stat"><b>{{ $pendingTx->total() }}</b><span>GD ví chờ duyệt</span></div>
</div>

<div class="panel chart-panel" style="margin-top:20px">
    <h3>Doanh thu theo ngày (30 ngày)</h3>
    <canvas id="financeRevenue" height="110"></canvas>
</div>

<h2>Giao dịch ví chờ duyệt</h2>
<table class="table">
    <tr><th>User</th><th>Loại</th><th>Số tiền</th><th>Ghi chú</th><th></th></tr>
    @foreach ($pendingTx as $tx)
        <tr>
            <td>{{ $tx->user->name }}</td>
            <td>{{ $tx->typeLabel() }}</td>
            <td>{{ number_format($tx->amount) }} ₫</td>
            <td>{{ $tx->note }}</td>
            <td>
                <form method="post" action="{{ route('admin.finance.approve', $tx) }}">@csrf<button class="btn btn-sm btn-accent">Duyệt</button></form>
            </td>
        </tr>
    @endforeach
</table>
{{ $pendingTx->links() }}

<script>
window.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const series = @json($series);
    new Chart(document.getElementById('financeRevenue'), {
        type: 'line',
        data: {
            labels: series.labels,
            datasets: [
                { label: 'Hoa hồng', data: series.commission, borderColor: '#1d6ef5', tension: .3, fill: false },
            ]
        },
        options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
    });
});
</script>
@endsection
