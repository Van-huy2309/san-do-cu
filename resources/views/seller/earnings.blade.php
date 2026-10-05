@extends('layouts.seller')
@section('title', 'Thu chi')
@section('content')
@include('admin.partials.chartjs')
<h1>Thu chi shop</h1>
<p class="muted">
    Thu là tiền đã chuyển về tài khoản shop sau khi trừ phí sàn. Chi là phí sàn trên các sản phẩm đã bán.
    @if ($account)
        Nhận về {{ $account->masked() }}.
    @else
        <a href="{{ route('seller.bank') }}">Đăng ký tài khoản ngân hàng</a> để nhận tiền.
    @endif
</p>
<p><a class="btn btn-accent btn-sm" href="{{ route('seller.earnings.export') }}">Xuất CSV</a></p>
<div class="stats">
    <div class="stat"><b>{{ number_format($income) }} ₫</b><span>Tổng thu đã về</span></div>
    <div class="stat"><b>{{ number_format($expense) }} ₫</b><span>Tổng chi phí sàn</span></div>
    <div class="stat"><b>{{ number_format($income + $expense) }} ₫</b><span>Doanh thu đã chia</span></div>
</div>
<div class="panel" style="margin:16px 0">
    <canvas id="shopCashChart" height="120"></canvas>
</div>
<h2>Sản phẩm đã bán</h2>
<table class="table">
    <tr><th>Thời điểm</th><th>Đơn</th><th>Sản phẩm</th><th>Doanh thu</th><th>Phí sàn</th><th>Tiền về</th><th>Trạng thái</th></tr>
    @forelse ($sales as $row)
        <tr>
            <td>{{ $row->when?->format('d/m/Y H:i') }}</td>
            <td>{{ $row->code }}</td>
            <td>{{ $row->title }}</td>
            <td>{{ number_format($row->gross) }} ₫</td>
            <td>{{ number_format($row->fee) }} ₫</td>
            <td>{{ number_format($row->net) }} ₫</td>
            <td>{{ $row->settled ? 'Đã về tài khoản' : 'Chờ người mua xác nhận' }}</td>
        </tr>
    @empty
        <tr><td colspan="7">Chưa có sản phẩm bán qua đơn hàng.</td></tr>
    @endforelse
</table>
@endsection
@push('scripts')
<script>
window.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const series = @json($series);
    new Chart(document.getElementById('shopCashChart'), {
        type: 'bar',
        data: {
            labels: series.labels,
            datasets: [
                { label: 'Thu — tiền về shop', data: series.income, backgroundColor: '#1d6ef5' },
                { label: 'Chi — phí sàn', data: series.expense, backgroundColor: '#f59e0b' },
            ]
        },
        options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
    });
});
</script>
@endpush
