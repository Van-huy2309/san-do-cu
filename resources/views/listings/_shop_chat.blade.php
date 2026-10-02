<section class="section shop-chat" id="chat-shop">
    <div class="section-head">
        <h2>Nhắn shop</h2>
        <span class="muted">Hỏi {{ $listing->seller->name }} về máy này</span>
    </div>

    @guest
        <div class="panel">
            <p>Đăng nhập để nhắn người bán tư vấn pin, phụ kiện và tình trạng máy.</p>
            <a class="btn btn-accent" href="{{ route('login', ['redirect' => route('listings.show', $listing).'#chat-shop']) }}">Đăng nhập để nhắn shop</a>
        </div>
    @else
        @if (auth()->id() === $listing->seller_id)
            @forelse ($shopThreads as $thread)
                <div class="panel" style="margin-bottom:12px">
                    <strong>{{ $thread->buyer->name }}</strong>
                    <div class="msg-list" style="margin-top:8px">
                        @foreach ($thread->messages as $m)
                            <div class="bubble {{ $m->user_id === auth()->id() ? 'me' : '' }}">
                                <div class="muted">{{ $m->user->name }}</div>
                                {{ $m->body }}
                            </div>
                        @endforeach
                    </div>
                    <form method="post" action="{{ route('messages.reply', $thread) }}" style="margin-top:12px">
                        @csrf
                        <input type="hidden" name="inline" value="1">
                        <textarea class="field" name="body" rows="2" required maxlength="1000" placeholder="Trả lời người mua..."></textarea>
                        <button class="btn btn-accent" type="submit">Gửi</button>
                    </form>
                </div>
            @empty
                <p class="muted">Chưa có ai nhắn về tin này.</p>
            @endforelse
        @elseif (auth()->user()->isAdmin())
            <p class="muted">Tài khoản quản trị xem tin, không nhắn shop từ trang này.</p>
        @else
            <div class="panel">
                <div class="msg-list" id="msg-list">
                    @forelse ($shopChat?->messages ?? [] as $m)
                        <div class="bubble {{ $m->user_id === auth()->id() ? 'me' : '' }}" data-id="{{ $m->id }}">
                            <div class="muted">{{ $m->user->name }}</div>
                            {{ $m->body }}
                        </div>
                    @empty
                        <p class="muted" data-empty>Chưa có tin nhắn. Gửi câu hỏi để shop trả lời.</p>
                    @endforelse
                </div>
                @if ($listing->isActive())
                    <form method="post" action="{{ $shopChat ? route('messages.reply', $shopChat) : route('messages.start', $listing) }}" style="margin-top:12px">
                        @csrf
                        @if ($shopChat)
                            <input type="hidden" name="inline" value="1">
                        @endif
                        <textarea class="field" name="body" rows="3" required maxlength="1000" placeholder="Hỏi về pin, máy, phụ kiện...">{{ old('body') }}</textarea>
                        <button class="btn btn-accent" type="submit">Gửi cho shop</button>
                    </form>
                @else
                    <p class="muted" style="margin-top:12px">Tin không còn nhận tin nhắn mới.</p>
                @endif
            </div>
            @if ($shopChat)
                @push('scripts')
                <script>
                (function () {
                    const list = document.getElementById('msg-list');
                    if (!list) return;
                    const pollUrl = @json(route('messages.poll', $shopChat));
                    const me = {{ (int) auth()->id() }};
                    const token = document.querySelector('meta[name="csrf-token"]')?.content;
                    function lastId() {
                        const nodes = list.querySelectorAll('[data-id]');
                        return nodes.length ? Number(nodes[nodes.length - 1].dataset.id) : 0;
                    }
                    function add(m) {
                        if (list.querySelector('[data-id="'+m.id+'"]')) return;
                        list.querySelector('[data-empty]')?.remove();
                        const div = document.createElement('div');
                        div.className = 'bubble' + (m.mine || m.user_id === me ? ' me' : '');
                        div.dataset.id = m.id;
                        const who = document.createElement('div');
                        who.className = 'muted';
                        who.textContent = m.user_name || '';
                        div.append(who, document.createTextNode(m.body || ''));
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
                })();
                </script>
                @endpush
            @endif
        @endif
    @endguest
</section>
