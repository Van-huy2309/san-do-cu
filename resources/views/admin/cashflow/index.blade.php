@extends('layouts.admin')
@section('title', 'Dòng tiền')
@section('content')
@include('admin.partials.chartjs')
<h1>Dòng tiền</h1>
<p class="muted">Sổ này nằm ở database tài chính riêng. Tiền người mua vào tài khoản admin, phí sàn được giữ lại, phần còn lại ghi nhận chuyển về tài khoản shop.</p>
<p><a class="btn btn-accent btn-sm" href="{{ route('admin.cashflow.export') }}">Xuất CSV</a></p>
<div class="stats">
    <div class="stat"><b>{{ number_format($totals['buyer']) }} ₫</b><span>Tổng tiền người mua</span></div>
    <div class="stat"><b>{{ number_format($totals['fee']) }} ₫</b><span>Phí sàn / lợi nhuận</span></div>
    <div class="stat"><b>{{ number_format($totals['payout']) }} ₫</b><span>Đã trả về shop</span></div>
    <div class="stat"><b>{{ number_format($totals['holding']) }} ₫</b><span>Đang giữ chưa chia</span></div>
    <div class="stat"><b>{{ number_format($totals['refund']) }} ₫</b><span>Đã hoàn người mua</span></div>
</div>
<div class="panel" style="margin-top:16px">
    <canvas id="cashflowChart" height="120"></canvas>
</div>
<h2>Sổ gần nhất</h2>
<table class="table">
    <tr><th>Thời điểm</th><th>Đơn</th><th>Shop</th><th>Loại</th><th>Số tiền</th></tr>
    @forelse ($entries as $entry)
        <tr>
            <td>{{ $entry->occurred_at?->format('d/m/Y H:i') }}</td>
            <td>#{{ $entry->order_id }}</td>
            <td>{{ $names[$entry->seller_id] ?? 'Tài khoản admin' }}</td>
            <td>{{ $entry->typeLabel() }}</td>
            <td>{{ number_format($entry->amount) }} ₫</td>
        </tr>
    @empty
        <tr><td colspan="5">Chưa có giao dịch.</td></tr>
    @endforelse
</table>
<h2>Tài khoản shop</h2>
<table class="table">
    <tr><th>Shop</th><th>Tài khoản</th></tr>
    @forelse ($accounts as $account)
        <tr>
            <td>{{ $shopNames[$account->user_id] ?? '#'.$account->user_id }}</td>
            <td>{{ $account->masked() }}</td>
        </tr>
    @empty
        <tr><td colspan="2">Chưa shop nào đăng ký ngân hàng.</td></tr>
    @endforelse
</table>
@endsection
@push('scripts')
<script>
window.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const series = @json($series);
    new Chart(document.getElementById('cashflowChart'), {
        type: 'line',
        data: {
            labels: series.labels,
            datasets: [
                { label: 'Tiền người mua', data: series.buyer, borderColor: '#1d6ef5', tension: .3, fill: false },
                { label: 'Phí sàn', data: series.fee, borderColor: '#0ea5a0', tension: .3, fill: false },
                { label: 'Trả shop', data: series.payout, borderColor: '#f59e0b', tension: .3, fill: false },
            ]
        },
        options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
    });
});
</script>
@endpush
