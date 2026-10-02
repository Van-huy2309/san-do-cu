@extends('layouts.admin')
@section('title', 'Chat '.$user->name)
@section('content')
<p class="muted"><a href="{{ route('admin.chat.index') }}">Chat khách</a> / {{ $user->name }}</p>
<h1>Chat với {{ $user->name }}</h1>
<p class="muted">{{ $user->email }}
    @if ($awaitingReply)
        · Tin mới nhất của khách chưa được phản hồi
    @else
        · Đã phản hồi tin gần nhất
    @endif
</p>

<div class="panel" style="margin-top:16px; display:flex; flex-direction:column; gap:10px; max-height:480px; overflow:auto">
    @forelse ($messages as $message)
        <div style="max-width:75%; padding:10px 12px; border-radius:12px; {{ $message->is_from_admin ? 'margin-left:auto; background:#1d6ef5; color:#fff' : 'background:#f4f6fb' }}">
            <div style="white-space:pre-wrap">{{ $message->body }}</div>
            <div style="font-size:.75rem; opacity:.75; margin-top:6px">
                {{ $message->is_from_admin ? 'Admin' : $user->name }} · {{ $message->created_at->format('d/m/Y H:i') }}
            </div>
        </div>
    @empty
        <p class="muted">Chưa có tin nhắn.</p>
    @endforelse
</div>

<form action="{{ route('admin.chat.store', $user) }}" method="POST" style="margin-top:12px">
    @csrf
    <label>Trả lời</label>
    <textarea class="field" name="body" required maxlength="2000" placeholder="Nhập nội dung trả lời">{{ old('body') }}</textarea>
    <button class="btn btn-accent" type="submit">Gửi phản hồi</button>
</form>
@endsection
