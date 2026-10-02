@extends('layouts.admin')
@section('title', 'Tin')
@section('content')
<h1>Tin đăng</h1>
@include('admin.partials.section-insights')
<table class="table">
    <tr><th>Tin</th><th>Người bán</th><th>Giá</th><th>Trạng thái</th><th></th></tr>
    @foreach ($listings as $listing)
        <tr>
            <td>{{ $listing->title }}</td>
            <td>{{ $listing->seller->name }} · {{ $listing->seller->email }}</td>
            <td>{{ number_format($listing->price) }}</td>
            <td>{{ $listing->statusLabel() }}</td>
            <td>
                @if ($listing->status === 'pending_review')
                    <form method="post" action="{{ route('admin.listings.approve', $listing) }}" style="display:inline">@csrf<button class="btn btn-sm btn-accent">Duyệt lên chợ</button></form>
                    <form method="post" action="{{ route('admin.listings.reject', $listing) }}" style="display:inline;margin-top:6px">
                        @csrf
                        <input class="field" name="note" placeholder="Lý do từ chối">
                        <button class="btn btn-sm">Từ chối</button>
                    </form>
                @endif
                <form method="post" action="{{ route('admin.listings.destroy', $listing) }}" style="margin-top:8px">
                    @csrf
                    <input class="field" name="reason" placeholder="Lý do gỡ tin" required minlength="3">
                    <button class="btn btn-sm btn-danger">Gỡ tin</button>
                </form>
            </td>
        </tr>
    @endforeach
</table>
{{ $listings->links() }}
@endsection
