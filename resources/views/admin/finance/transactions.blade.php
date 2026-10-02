@extends('layouts.admin')
@section('title', 'Giao dịch thanh toán')
@section('content')
<h1>Danh sách giao dịch</h1>
<p class="muted">Cập nhật thủ công chỉ áp dụng cho đơn COD. MoMo được đối soát qua cổng thanh toán.</p>
<p>
    <a class="btn btn-sm btn-ghost" href="{{ route('admin.finance', request()->query()) }}">Thống kê</a>
    <a class="btn btn-sm btn-accent" href="{{ route('admin.finance.transactions', request()->query()) }}">Giao dịch</a>
    <a class="btn btn-sm btn-ghost" href="{{ route('admin.finance.wallet') }}">Đối soát ví</a>
</p>

<form method="GET" action="{{ route('admin.finance.transactions') }}" class="panel" style="margin:16px 0">
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
            <label>Sắp xếp</label>
            <select class="field" name="sort">
                @foreach (['newest' => 'Mới nhất', 'oldest' => 'Cũ nhất', 'amount_desc' => 'Tiền cao → thấp', 'amount_asc' => 'Tiền thấp → cao'] as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['sort'] ?? 'newest') === $key)>{{ $label }}</option>
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
    </div>
    <button class="btn btn-accent" type="submit">Lọc</button>
    <a class="btn btn-ghost" href="{{ route('admin.finance.transactions') }}">Xóa lọc</a>
</form>

<table class="table">
    <tr>
        <th>Mã đơn</th>
        <th>Người nhận</th>
        <th>Tổng tiền</th>
        <th>Phương thức</th>
        <th>Thanh toán</th>
        <th>Ngày tạo</th>
        <th>Cập nhật COD</th>
    </tr>
    @forelse ($orders as $order)
        @php
            $isCod = ($order->gateway ?? '') === 'cod';
            $currentPay = $order->payment_status ?? 'pending';
            $allowed = $codTransitions[$currentPay] ?? [];
        @endphp
        <tr>
            <td>
                <a href="{{ route('admin.orders.show', $order->id) }}"><strong>{{ $order->code }}</strong></a>
                <div class="muted">#{{ $order->id }}</div>
            </td>
            <td>
                {{ $order->name }}
                <div class="muted">{{ $order->phone }}</div>
            </td>
            <td>{{ number_format($order->total_price, 0, ',', '.') }} ₫</td>
            <td>{{ $methods[$order->gateway] ?? strtoupper($order->gateway ?? '—') }}</td>
            <td>
                {{ $statuses[$currentPay] ?? $currentPay }}
                @if ($order->paid_at)
                    <div class="muted">{{ \Carbon\Carbon::parse($order->paid_at)->format('d/m/Y H:i') }}</div>
                @endif
            </td>
            <td>{{ \Carbon\Carbon::parse($order->created_at)->format('d/m/Y H:i') }}</td>
            <td>
                @if ($isCod && count($allowed) > 1)
                    <form action="{{ route('admin.finance.update-status', $order->id) }}" method="POST" style="display:flex; gap:6px; align-items:center">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="current_payment_status" value="{{ $currentPay }}">
                        <input type="hidden" name="current_order_status" value="{{ $order->status }}">
                        <input type="hidden" name="current_payment_id" value="{{ (int) ($order->payment_id ?? 0) }}">
                        <select name="payment_status" class="field" style="margin:0; min-width:150px">
                            @foreach ($allowed as $st)
                                <option value="{{ $st }}" @selected($st === $currentPay)>{{ $statuses[$st] ?? $st }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-accent">Lưu</button>
                    </form>
                @elseif ($isCod)
                    <span class="muted">Không đổi được</span>
                @else
                    <span class="muted">Chỉ COD</span>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">Không có giao dịch.</td></tr>
    @endforelse
</table>
{{ $orders->links() }}
@endsection
