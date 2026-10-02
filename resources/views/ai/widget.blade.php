@php
    $history = is_array($history ?? null) ? $history : [];
    $tone = $tone ?? 'care';
    $tool = $tool ?? 'relic.care';
@endphp
<div class="ai-dock ai-dock-{{ $tone }} @if (!empty($open)) is-open @endif" data-ai data-endpoint="{{ $endpoint }}" data-tool="{{ $tool }}" data-tone="{{ $tone }}" data-open="{{ !empty($open) ? '1' : '0' }}">
    <button type="button" class="ai-fab" data-ai-toggle aria-expanded="{{ !empty($open) ? 'true' : 'false' }}" aria-label="Mở {{ $title }}">
        <span class="ai-fab-icon">@include('ai.icon', ['size' => 22])</span>
        <span class="ai-fab-close" aria-hidden="true">×</span>
        <span class="ai-fab-pulse"></span>
    </button>
    <section class="ai-panel" @if (empty($open)) hidden @endif>
        <header class="ai-head">
            <div class="ai-mark">{{ $mark }}</div>
            <div>
                <strong>{{ $title }}</strong>
                <div class="muted" style="font-size:.75rem">{{ $subtitle }}</div>
            </div>
            <button type="button" class="ai-x" data-ai-close aria-label="Đóng chat">×</button>
        </header>
        <div class="ai-log" data-ai-log>
            @forelse ($history as $item)
                @php
                    $who = ($item['who'] ?? '') === 'me' ? 'me' : 'bot';
                    $links = $item['links'] ?? [];
                    $products = $item['products'] ?? [];
                @endphp
                <div class="ai-bubble {{ $who }} @if (!empty($products)) has-products @endif">
                    {{ $item['text'] ?? '' }}
                    @if (!empty($products))
                        <div class="ai-products">
                            @foreach ($products as $p)
                                <a class="ai-product" href="{{ $p['url'] ?? '#' }}">
                                    @if (!empty($p['image']))
                                        <img src="{{ $p['image'] }}" alt="{{ $p['title'] ?? '' }}" loading="lazy">
                                    @endif
                                    <span class="ai-product-body">
                                        <strong>{{ $p['title'] ?? '' }}</strong>
                                        <span class="ai-price">{{ $p['price'] ?? '' }}</span>
                                        <em>{{ $p['meta'] ?? '' }}{{ !empty($p['seal']) ? ' · Seal' : '' }}</em>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if (!empty($links))
                        <div class="ai-links">
                            @foreach ($links as $l)
                                <a href="{{ $l['url'] ?? '#' }}">{{ $l['label'] ?? 'Link' }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="ai-bubble bot @if (!empty($starter)) has-products @endif" data-ai-hello>
                    {{ $hello }}
                    @if (!empty($starter))
                        <div class="ai-products">
                            @foreach ($starter as $p)
                                <a class="ai-product" href="{{ $p['url'] }}">
                                    @if (!empty($p['image']))
                                        <img src="{{ $p['image'] }}" alt="{{ $p['title'] }}" loading="lazy">
                                    @endif
                                    <span class="ai-product-body">
                                        <strong>{{ $p['title'] }}</strong>
                                        <span class="ai-price">{{ $p['price'] }}</span>
                                        <em>{{ $p['meta'] }}{{ !empty($p['seal']) ? ' · Seal' : '' }}</em>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforelse
        </div>
        @if (!empty($chips))
        <div class="ai-chips">
            @foreach ($chips as $chip)
                <button type="button" class="ai-chip" data-ai-chip="{{ $chip }}">{{ $chip }}</button>
            @endforeach
        </div>
        @endif
        <form class="ai-form" data-ai-form>
            <input class="field" name="message" maxlength="{{ (int) ($maxMessage ?? config('ai.care.max_message', 2000)) }}" placeholder="{{ $placeholder ?? 'Nhắn như chat với bạn — hỏi dài cũng được…' }}" autocomplete="off" required>
            <button class="btn btn-accent btn-sm" type="submit">Gửi</button>
        </form>
    </section>
</div>
@once
<script>
(function () {
    if (window.__relicAiBound) return;
    window.__relicAiBound = true;

    function bindDock(dock) {
        const log = dock.querySelector('[data-ai-log]');
        const form = dock.querySelector('[data-ai-form]');
        const panel = dock.querySelector('.ai-panel');
        const fab = dock.querySelector('.ai-fab');
        const endpoint = dock.dataset.endpoint;
        const tool = dock.dataset.tool || 'relic.care';
        const tone = dock.dataset.tone || 'care';
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const title = fab?.getAttribute('aria-label')?.replace(/^Mở\s+/, '') || 'Relic';
        const waitingText = tone === 'ops'
            ? 'Đang đọc dữ liệu vận hành…'
            : 'Đang tìm trên Relic…';
        const submitBtn = form?.querySelector('button[type="submit"]');
        let rpcId = 0;
        let session = null;
        let turn = 0;
        let flight = null;

        function isOpen() {
            return dock.classList.contains('is-open');
        }
        function setOpen(on) {
            dock.classList.toggle('is-open', on);
            dock.dataset.open = on ? '1' : '0';
            if (on) panel.removeAttribute('hidden');
            else panel.setAttribute('hidden', '');
            if (fab) {
                fab.setAttribute('aria-expanded', on ? 'true' : 'false');
                fab.setAttribute('aria-label', (on ? 'Đóng ' : 'Mở ') + title);
            }
            if (on) {
                const input = form.querySelector('[name=message]');
                setTimeout(() => input && input.focus(), 50);
            }
        }

        function appendExtras(div, extra) {
            const links = extra?.links || [];
            const products = extra?.products || [];
            if (products.length) {
                div.classList.add('has-products');
                const box = document.createElement('div');
                box.className = 'ai-products';
                products.forEach((p) => {
                    const a = document.createElement('a');
                    a.className = 'ai-product';
                    a.href = p.url;
                    const img = document.createElement('img');
                    img.src = p.image || '';
                    img.alt = p.title || '';
                    img.loading = 'lazy';
                    const body = document.createElement('span');
                    body.className = 'ai-product-body';
                    body.innerHTML = '<strong></strong><span class="ai-price"></span><em></em>';
                    body.querySelector('strong').textContent = p.title;
                    body.querySelector('.ai-price').textContent = p.price;
                    body.querySelector('em').textContent = (p.meta || '') + (p.seal ? ' · Seal' : '');
                    if (p.image) a.appendChild(img);
                    else a.style.gridTemplateColumns = '1fr';
                    a.appendChild(body);
                    box.appendChild(a);
                });
                div.appendChild(box);
            }
            if (links.length) {
                const nav = document.createElement('div');
                nav.className = 'ai-links';
                links.forEach((l) => {
                    const a = document.createElement('a');
                    a.href = l.url;
                    a.textContent = l.label;
                    nav.appendChild(a);
                });
                div.appendChild(nav);
            }
        }

        function add(text, who, extra) {
            const div = document.createElement('div');
            div.className = 'ai-bubble ' + who;
            if (text) div.appendChild(document.createTextNode(text));
            appendExtras(div, extra);
            log.appendChild(div);
            log.scrollTop = log.scrollHeight;
        }

        function isDownloadUrl(url) {
            return /\/analytics\/export/i.test(url || '') || /\.csv(\?|$)/i.test(url || '');
        }

        function goOpen(url) {
            if (isDownloadUrl(url)) {
                const a = document.createElement('a');
                a.href = url;
                a.rel = 'noopener';
                document.body.appendChild(a);
                a.click();
                a.remove();
                return;
            }
            // Session PHP đã lưu chat trước khi mở trang — chỉ cần chuyển trang.
            window.location.assign(url);
        }

        fab?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            setOpen(!isOpen());
        });
        dock.querySelectorAll('[data-ai-close]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                setOpen(false);
            });
        });
        document.querySelectorAll('[data-ai-open]').forEach((btn) => {
            if (btn.dataset.aiBound) return;
            btn.dataset.aiBound = '1';
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                setOpen(true);
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isOpen()) setOpen(false);
        });
        document.addEventListener('click', (e) => {
            if (!isOpen()) return;
            if (dock.contains(e.target) || e.target.closest('[data-ai-open]')) return;
            setOpen(false);
        });
        dock.addEventListener('click', (e) => e.stopPropagation());

        function rpc(method, params, notification, signal) {
            const body = { jsonrpc: '2.0', method };
            if (!notification) body.id = ++rpcId;
            if (params !== undefined) body.params = params;
            return fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json, text/event-stream',
                    'X-CSRF-TOKEN': token,
                    'MCP-Protocol-Version': '2025-03-26',
                },
                body: JSON.stringify(body),
                credentials: 'same-origin',
                signal,
            }).then(async (res) => {
                if (notification || res.status === 202 || res.status === 204) return null;
                const json = await res.json().catch(() => null);
                if (!json || json.error) {
                    const message = json?.error?.message || 'MCP từ chối yêu cầu.';
                    const error = new Error(message);
                    error.mcp = json?.error || null;
                    throw error;
                }
                return json.result;
            });
        }

        function ensureSession() {
            if (!session) {
                session = rpc('initialize', {
                    protocolVersion: '2025-03-26',
                    capabilities: {},
                    clientInfo: { name: 'relic-web', version: '1.0.0' },
                }).then(() => rpc('notifications/initialized', undefined, true))
                    .catch((error) => {
                        session = null;
                        throw error;
                    });
            }
            return session;
        }

        async function send(message) {
            const mine = ++turn;
            if (flight) flight.abort();
            flight = new AbortController();
            setOpen(true);
            add(message, 'me');
            const wait = document.createElement('div');
            wait.className = 'ai-bubble bot muted';
            wait.textContent = waitingText;
            log.appendChild(wait);
            log.scrollTop = log.scrollHeight;
            if (submitBtn) submitBtn.disabled = true;
            try {
                await ensureSession();
                if (mine !== turn) {
                    wait.remove();
                    return;
                }
                const result = await rpc('tools/call', {
                    name: tool,
                    arguments: { message },
                }, false, flight.signal);
                if (mine !== turn) {
                    wait.remove();
                    return;
                }
                wait.remove();
                const text = (result?.content || []).find((block) => block.type === 'text')?.text || 'Không trả lời được.';
                const extra = result?.structuredContent || {};
                if (result?.isError) {
                    add(text, 'bot');
                    return;
                }
                add(text, 'bot', extra);
                if (extra.open) {
                    setTimeout(() => goOpen(extra.open), 120);
                }
            } catch (error) {
                if (error?.name === 'AbortError' || mine !== turn) {
                    wait.remove();
                    return;
                }
                wait.textContent = error?.mcp ? error.message : 'Mất kết nối. Thử lại.';
            } finally {
                if (mine === turn && submitBtn) submitBtn.disabled = false;
            }
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const input = form.querySelector('[name=message]');
            const msg = input.value.trim();
            if (!msg) return;
            input.value = '';
            send(msg);
        });
        dock.querySelectorAll('[data-ai-chip]').forEach((btn) => {
            btn.addEventListener('click', () => send(btn.dataset.aiChip));
        });

        if (dock.dataset.open === '1') setOpen(true);
        log.scrollTop = log.scrollHeight;
    }

    document.querySelectorAll('[data-ai]').forEach(bindDock);
})();
</script>
@endonce
