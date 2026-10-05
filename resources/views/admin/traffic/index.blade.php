@extends('layouts.admin')
@section('title', 'Chặn tấn công')
@section('content')
<h1>Chặn tấn công</h1>
<p class="muted">Mỗi tài khoản được 240 request/phút và 50 request/10 giây. Khách chưa đăng nhập tính theo IP: 300 request/phút và 60 request/10 giây. Đăng nhập sai 5 lần trong 10 phút thì IP đó bị khóa 15 phút. Khóa tay vẫn là vĩnh viễn và không cần lý do.</p>
<h2>Cảnh báo vượt ngưỡng</h2>
<table class="table">
    <tr><th>Thời điểm</th><th>Tài khoản</th><th>IP</th><th>Mức</th><th>Số lần</th><th>Đường dẫn</th><th></th></tr>
    @forelse ($alerts as $alert)
        <tr>
            <td>{{ $alert->created_at?->format('d/m/Y H:i') }}</td>
            <td>{{ $alert->user?->email ?: 'Khách' }}</td>
            <td>{{ $alert->ip }}</td>
            <td>{{ $alert->scope === 'account' ? 'Tài khoản' : ($alert->scope === 'login' ? 'Đăng nhập' : 'IP khách') }}</td>
            <td>{{ number_format($alert->hits) }}</td>
            <td>{{ $alert->sample_path }}</td>
            <td>
                @if ($alert->user && ! $alert->user->isAdmin())
                    <form method="post" action="{{ route('admin.traffic.ban-user', $alert->user) }}" onsubmit="return confirm('Khóa vĩnh viễn tài khoản này?')">
                        @csrf
                        <button class="btn btn-ghost btn-sm" type="submit">Khóa tài khoản</button>
                    </form>
                @endif
                @if ($alert->ip !== request()->ip())
                    <form method="post" action="{{ route('admin.traffic.ban-ip') }}" onsubmit="return confirm('Khóa vĩnh viễn IP này?')">
                        @csrf
                        <input type="hidden" name="ip" value="{{ $alert->ip }}">
                        <button class="btn btn-ghost btn-sm" type="submit">Khóa IP</button>
                    </form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7">Chưa có cảnh báo.</td></tr>
    @endforelse
</table>
<h2>Tài khoản đang khóa</h2>
<table class="table">
    <tr><th>Tài khoản</th><th>Lúc khóa</th><th></th></tr>
    @forelse ($accounts as $account)
        <tr>
            <td>{{ $account->name }} · {{ $account->email }}</td>
            <td>{{ $account->banned_at?->format('d/m/Y H:i') }}</td>
            <td>
                <form method="post" action="{{ route('admin.traffic.lift-user', $account) }}">@csrf
                    <button class="btn btn-ghost btn-sm" type="submit">Mở khóa</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="3">Không có tài khoản bị khóa.</td></tr>
    @endforelse
</table>
<h2>IP đang khóa</h2>
<table class="table">
    <tr><th>IP</th><th>Lúc khóa</th><th>Hết hạn</th><th></th></tr>
    @forelse ($ips as $ban)
        <tr>
            <td>{{ $ban->ip }}</td>
            <td>{{ $ban->created_at?->format('d/m/Y H:i') }}</td>
            <td>{{ $ban->banned_until ? $ban->banned_until->timezone(config('app.timezone'))->format('H:i d/m/Y') : 'Vĩnh viễn' }}</td>
            <td>
                <form method="post" action="{{ route('admin.traffic.lift-ip', $ban) }}">@csrf
                    <button class="btn btn-ghost btn-sm" type="submit">Mở khóa</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="4">Không có IP bị khóa.</td></tr>
    @endforelse
</table>
@endsection
