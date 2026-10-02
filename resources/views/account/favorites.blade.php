@extends('layouts.account')
@section('title', 'Yêu thích')
@section('content')
<h1>Tin đã lưu</h1>
<div class="grid">
    @forelse ($listings as $listing)
        @include('listings._card')
    @empty
        <p class="empty">Chưa lưu tin nào. Bấm ♡ trên thẻ sản phẩm.</p>
    @endforelse
</div>
{{ $listings->links() }}
@endsection
