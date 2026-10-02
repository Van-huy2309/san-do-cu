@extends('layouts.admin')
@section('title', 'KYC')
@section('content')
<h1>Duyệt định danh</h1>
@include('admin.partials.section-insights')
<table class="table">
    <tr><th>Người dùng</th><th>Trạng thái</th><th>CCCD</th><th></th></tr>
    @foreach ($users as $user)
        <tr>
            <td>{{ $user->name }}<br><span class="muted">{{ $user->email }}</span></td>
            <td>{{ $user->kycLabel() }}</td>
            <td>
                @if ($user->kyc_front_path)<a href="{{ url($user->kyc_front_path) }}" target="_blank">Mặt trước</a>@endif
                @if ($user->kyc_back_path) · <a href="{{ url($user->kyc_back_path) }}" target="_blank">Mặt sau</a>@endif
                @if ($user->kyc_id_last4)<div class="muted">****{{ $user->kyc_id_last4 }}</div>@endif
            </td>
            <td>
                @if ($user->kyc_status === 'pending')
                    <form method="post" action="{{ route('admin.kyc.approve', $user) }}" style="display:inline">@csrf<button class="btn btn-sm btn-accent">Duyệt</button></form>
                    <form method="post" action="{{ route('admin.kyc.reject', $user) }}">
                        @csrf
                        <input class="field" name="note" placeholder="Lý do từ chối" required>
                        <button class="btn btn-sm btn-danger">Từ chối</button>
                    </form>
                @endif
            </td>
        </tr>
    @endforeach
</table>
{{ $users->links() }}
@endsection
