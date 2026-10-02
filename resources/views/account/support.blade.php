@extends('layouts.account')
@section('title', 'Chat với admin')
@section('content')
<h1>Chat với admin</h1>
<p class="muted">Tin này gửi thẳng cho quản trị viên, tách khỏi chat mua bán với người bán.</p>

<div class="panel" style="margin-top:16px; display:flex; flex-direction:column; gap:10px; max-height:420px; overflow:auto">
    @forelse ($messages as $message)
        <div style="max-width:75%; padding:10px 12px; border-radius:12px; {{ $message->is_from_admin ? 'background:#f4f6fb' : 'margin-left:auto; background:#1d6ef5; color:#fff' }}">
            <div style="white-space:pre-wrap">{{ $message->body }}</div>
            <div style="font-size:.75rem; opacity:.75; margin-top:6px">
                {{ $message->is_from_admin ? 'Admin' : 'Bạn' }} · {{ $message->created_at->format('d/m/Y H:i') }}
            </div>
        </div>
    @empty
        <p class="muted">Bạn chưa nhắn admin. Gửi câu hỏi về đơn hàng, vận chuyển hoặc thanh toán.</p>
    @endforelse
</div>

<form action="{{ route('support.store') }}" method="POST" style="margin-top:12px">
    @csrf
    <label>Nội dung</label>
    <textarea class="field" name="body" required maxlength="2000" placeholder="Nhập tin nhắn">{{ old('body') }}</textarea>
    <button class="btn btn-accent" type="submit">Gửi</button>
</form>
@endsection
