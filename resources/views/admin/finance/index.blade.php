@extends('layouts.admin')
@section('title', 'Tài chính')
@section('content')
<h1>Thống kê tài chính</h1>
<p class="muted">Mỗi đơn chỉ tính một lần (ưu tiên giao dịch đã thu, chờ hoàn hoặc đã hoàn). Lọc theo ngày tạo đơn.</p>
<p>
    <a class="btn btn-sm {{ request()->routeIs('admin.finance') ? 'btn-accent' : 'btn-ghost' }}" href="{{ route('admin.finance', request()->query()) }}">Thống kê</a>
    <a class="btn btn-sm btn-ghost" href="{{ route('admin.finance.transactions', request()->query()) }}">Giao dịch</a>
    <a class="btn btn-sm btn-ghost" href="{{ route('admin.finance.wallet') }}">Đối soát ví</a>
    <a class="btn btn-sm btn-accent" href="{{ route('admin.finance.export', request()->query()) }}">Xuất CSV</a>
</p>

<form method="GET" action="{{ route('admin.finance') }}" class="panel" style="margin:16px 0">
    <div class="form-grid">
        <div>
            <label>Tìm kiếm</label>
            <input class="field" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Mã đơn, tên, SĐT">
        </div>
        <div>
            <label>Phương thức</label>
            <select class="field" name="gateway">
                <option value="">Tất cả</option>
                @foreach ($methods as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['gateway'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Trạng thái thanh toán</label>
            <select class="field" name="payment_status">
                <option value="">Tất cả</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['payment_status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Từ ngày</label>
            <input class="field" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div>
            <label>Đến ngày</label>
            <input class="field" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div>
            <label>Số tiền từ</label>
            <input class="field" type="number" step="0.01" min="0" name="min_amount" value="{{ $filters['min_amount'] ?? '' }}">
        </div>
        <div>
            <label>Số tiền đến</label>
            <input class="field" type="number" step="0.01" min="0" name="max_amount" value="{{ $filters['max_amount'] ?? '' }}">
        </div>
    </div>
    <button class="btn btn-accent" type="submit">Lọc</button>
    <a class="btn btn-ghost" href="{{ route('admin.finance') }}">Xóa lọc</a>
</form>

<div class="stats">
    <div class="stat"><b>{{ number_format($summary->order_count ?? 0) }}</b><span>Số đơn theo bộ lọc</span></div>
    <div class="stat"><b>{{ number_format($summary->total_amount ?? 0, 0, ',', '.') }} ₫</b><span>Tổng giá trị đơn</span></div>
    <div class="stat"><b>{{ number_format($commission ?? 0, 0, ',', '.') }} ₫</b><span>Phí sàn 5%</span></div>
</div>

<div class="form-grid" style="margin-top:18px; align-items:start">
    <div class="panel">
        <h3>Theo trạng thái thanh toán</h3>
        <table class="table">
            <tr><th>Trạng thái</th><th>Số đơn</th><th>Tổng tiền</th></tr>
            @php $shown = false; @endphp
            @foreach ($statuses as $key => $label)
                @php $row = $statusTotals->get($key); @endphp
                @if ($row)
                    @php $shown = true; @endphp
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ number_format($row->order_count) }}</td>
                        <td>{{ number_format($row->total_amount, 0, ',', '.') }} ₫</td>
                    </tr>
                @endif
            @endforeach
            @unless ($shown)
                <tr><td colspan="3" class="muted">Chưa có dữ liệu.</td></tr>
            @endunless
        </table>
    </div>
    <div class="panel">
        <h3>Theo phương thức</h3>
        <table class="table">
            <tr><th>Phương thức</th><th>Số đơn</th><th>Tổng giá trị</th><th>Đã thu</th></tr>
            @php $shownMethod = false; @endphp
            @foreach ($methods as $key => $label)
                @php $row = $methodTotals->get($key); @endphp
                @if ($row)
                    @php $shownMethod = true; @endphp
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ number_format($row->order_count) }}</td>
                        <td>{{ number_format($row->total_amount, 0, ',', '.') }} ₫</td>
                        <td>{{ number_format($row->paid_amount ?? 0, 0, ',', '.') }} ₫</td>
                    </tr>
                @endif
            @endforeach
            @unless ($shownMethod)
                <tr><td colspan="4" class="muted">Chưa có dữ liệu.</td></tr>
            @endunless
        </table>
    </div>
</div>
@endsection
