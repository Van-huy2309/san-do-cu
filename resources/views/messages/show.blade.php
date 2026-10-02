@extends('layouts.account')
@section('title', 'Chat')
@section('content')
<h1>{{ $conversation->listing->title }}</h1>
<p class="muted">Với {{ $conversation->otherParty(auth()->id())->name }}
    @if ($conversation->accepted_price)
        · Giá đã chốt: <strong>{{ number_format($conversation->accepted_price, 0, ',', '.') }} ₫</strong>
    @endif
    · <span class="badge" id="chat-live">Realtime + poll</span>
</p>
<div class="panel msg-list" id="msg-list">
    @foreach ($messages as $m)
        <div class="bubble {{ $m->user_id === auth()->id() ? 'me' : '' }}" data-id="{{ $m->id }}">
            <div class="muted">{{ $m->user->name }}</div>
            {{ $m->body }}
            @if ($m->offer_amount && $m->offer_status === 'pending' && auth()->id() === $conversation->seller_id)
                <form method="post" action="{{ route('messages.accept', $conversation) }}" style="margin-top:8px">
                    @csrf
                    <input type="hidden" name="message_id" value="{{ $m->id }}">
                    <button class="btn btn-sm btn-accent">Đồng ý giá này</button>
                </form>
            @elseif ($m->offer_status)
                <div class="muted">Offer: {{ $m->offer_status }}</div>
            @endif
        </div>
    @endforeach
</div>
<form method="post" action="{{ route('messages.reply', $conversation) }}" style="margin-top:12px" id="chat-form">
    @csrf
    <textarea class="field" name="body" required></textarea>
    <button class="btn btn-accent">Gửi</button>
</form>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script>
(function () {
    const list = document.getElementById('msg-list');
    const pollUrl = @json(route('messages.poll', $conversation));
    const me = {{ (int) auth()->id() }};
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    function lastId() {
        const nodes = list.querySelectorAll('[data-id]');
        return nodes.length ? Number(nodes[nodes.length - 1].dataset.id) : 0;
    }
    function add(m) {
        if (list.querySelector('[data-id="'+m.id+'"]')) return;
        const div = document.createElement('div');
        div.className = 'bubble' + (m.mine || m.user_id === me ? ' me' : '');
        div.dataset.id = m.id;
        div.innerHTML = '<div class="muted"></div>';
        div.querySelector('.muted').textContent = m.user_name || '';
        div.append(document.createTextNode(m.body || ''));
        list.appendChild(div);
        list.scrollTop = list.scrollHeight;
    }
    async function poll() {
        const res = await fetch(pollUrl + '?after=' + lastId(), { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }});
        if (!res.ok) return;
        const json = await res.json();
        (json.messages || []).forEach(add);
    }
    setInterval(poll, 3000);

    const key = @json(config('broadcasting.connections.reverb.key'));
    if (!key || typeof Echo === 'undefined') return;
    try {
        const echo = new Echo({
            broadcaster: 'reverb',
            key: key,
            wsHost: @json(config('broadcasting.connections.reverb.options.host') ?: '127.0.0.1'),
            wsPort: {{ (int) config('broadcasting.connections.reverb.options.port', 8080) }},
            wssPort: {{ (int) config('broadcasting.connections.reverb.options.port', 8080) }},
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: @json(url('/broadcasting/auth')),
            auth: { headers: { 'X-CSRF-TOKEN': token } }
        });
        echo.private('chat.{{ $conversation->id }}').listen('.message.created', (e) => add(e));
        document.getElementById('chat-live').textContent = 'Reverb live';
    } catch (e) {}
})();
</script>
@endpush
@endsection
