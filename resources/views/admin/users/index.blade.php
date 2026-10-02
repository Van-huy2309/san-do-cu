@extends('layouts.admin')
@section('title', 'Users')
@section('content')
<h1>Người dùng</h1>
@include('admin.partials.section-insights')
<table class="table">
    <tr><th>Tên</th><th>Email</th><th>Xác thực</th><th>Khóa</th><th></th></tr>
    @foreach ($users as $user)
        <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>{{ $user->email_verified_at ? 'đã xác thực' : 'chưa' }}</td>
            <td>{{ $user->is_banned ? 'khóa' : 'mở' }}</td>
            <td>
                @unless ($user->isAdmin())
                    @if ($user->is_banned)
                        <form method="post" action="{{ route('admin.users.unlock', $user) }}" style="display:inline">@csrf<button class="btn btn-sm">Mở khóa</button></form>
                    @else
                        <form method="post" action="{{ route('admin.users.lock', $user) }}" style="margin-bottom:8px">
                            @csrf
                            <input class="field" name="reason" placeholder="Lý do khóa (bắt buộc)" required minlength="3">
                            <button class="btn btn-sm btn-danger">Khóa</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Xóa tài khoản này?')">@csrf<button class="btn btn-ghost btn-sm">Xóa TK</button></form>
                @endunless
            </td>
        </tr>
    @endforeach
</table>
{{ $users->links() }}
@endsection
