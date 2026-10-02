@extends('layouts.admin')
@section('title', 'Chat khách hàng')
@section('content')
<h1>Chat với khách hàng</h1>
<p class="muted">Hội thoại tách theo tin admin đã trả lời và tin khách gửi mà chưa được phản hồi. Tìm theo tên người dùng.</p>

<p>
    <a class="btn btn-sm {{ $activeTab === 'unreplied' ? 'btn-accent' : 'btn-ghost' }}"
       href="{{ route('admin.chat.index', array_filter(['tab' => 'unreplied', 'search' => $filters['search'] ?? null])) }}">
        Chưa phản hồi ({{ $counts['unreplied'] }})
    </a>
    <a class="btn btn-sm {{ $activeTab === 'replied' ? 'btn-accent' : 'btn-ghost' }}"
       href="{{ route('admin.chat.index', array_filter(['tab' => 'replied', 'search' => $filters['search'] ?? null])) }}">
        Đã phản hồi ({{ $counts['replied'] }})
    </a>
</p>

<form method="GET" action="{{ route('admin.chat.index') }}" class="panel" style="margin:16px 0; display:flex; gap:8px; align-items:end; flex-wrap:wrap">
    <input type="hidden" name="tab" value="{{ $activeTab }}">
    <div style="flex:1; min-width:220px">
        <label>Tìm theo tên người dùng</label>
        <input class="field" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tên khách hàng">
    </div>
    <button class="btn btn-accent" type="submit">Tìm</button>
    <a class="btn btn-ghost" href="{{ route('admin.chat.index', ['tab' => $activeTab]) }}">Xóa</a>
</form>

<table class="table">
    <tr>
        <th>Khách hàng</th>
        <th>Tin gần nhất</th>
        <th>Trạng thái</th>
        <th>Chưa đọc</th>
        <th>Thời gian</th>
        <th></th>
    </tr>
    @forelse ($threads as $thread)
        <tr>
            <td>
                <strong>{{ $thread->name }}</strong>
                <div class="muted">{{ $thread->email }}</div>
            </td>
            <td style="max-width:280px">{{ \Illuminate\Support\Str::limit($thread->last_message_body, 120) }}</td>
            <td>{{ $thread->last_is_from_admin ? 'Đã phản hồi' : 'Chưa phản hồi' }}</td>
            <td>{{ $thread->unread_count }}</td>
            <td>{{ $thread->last_message_at ? \Carbon\Carbon::parse($thread->last_message_at)->format('d/m/Y H:i') : '—' }}</td>
            <td><a class="btn btn-sm btn-accent" href="{{ route('admin.chat.show', $thread) }}">Mở chat</a></td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="muted">
                @if ($activeTab === 'replied')
                    Không có hội thoại đã phản hồi{{ filled($filters['search'] ?? null) ? ' khớp tên này' : '' }}.
                @else
                    Không có tin chưa phản hồi{{ filled($filters['search'] ?? null) ? ' khớp tên này' : '' }}.
                @endif
            </td>
        </tr>
    @endforelse
</table>
{{ $threads->links() }}
@endsection
