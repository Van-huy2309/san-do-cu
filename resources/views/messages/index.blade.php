@extends('layouts.account')
@section('title', 'Tin nhắn')
@section('content')
<h1>Hộp thư</h1>
@forelse ($conversations as $c)
    <a class="panel" style="display:block;margin-bottom:8px" href="{{ route('messages.show', $c) }}">
        <strong>{{ $c->listing->title }}</strong>
        <div class="muted">{{ $c->otherParty(auth()->id())->name }} · {{ optional($c->last_message_at)->diffForHumans() }}</div>
    </a>
@empty
    <p class="empty">Chưa có hội thoại.</p>
@endforelse
<div class="pager">{{ $conversations->links() }}</div>
@endsection
