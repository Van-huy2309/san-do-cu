<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['unreplied', 'replied'])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $activeTab = $filters['tab'] ?? 'unreplied';

        $latest = DB::table('chat_messages as latest')
            ->select('latest.customer_id', 'latest.body', 'latest.created_at', 'latest.is_from_admin')
            ->whereRaw('latest.id = (select max(id) from chat_messages where customer_id = latest.customer_id)');

        $base = User::query()
            ->where('users.role', '!=', 'admin')
            ->joinSub($latest, 'last_msg', 'last_msg.customer_id', '=', 'users.id');

        if ($request->filled('search')) {
            $search = trim($filters['search']);
            $base->where('users.name', 'like', '%'.$search.'%');
        }

        $counts = [
            'unreplied' => (clone $base)->where('last_msg.is_from_admin', false)->count(),
            'replied' => (clone $base)->where('last_msg.is_from_admin', true)->count(),
        ];

        $threads = (clone $base)
            ->where('last_msg.is_from_admin', $activeTab === 'replied')
            ->select(
                'users.*',
                'last_msg.body as last_message_body',
                'last_msg.created_at as last_message_at',
                'last_msg.is_from_admin as last_is_from_admin'
            )
            ->withCount([
                'supportMessages as unread_count' => function ($query) {
                    $query->where('is_from_admin', false)->whereNull('read_at');
                },
            ])
            ->orderByDesc('last_msg.created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.chat.index', compact('threads', 'filters', 'activeTab', 'counts'));
    }

    public function show(User $user): View|RedirectResponse
    {
        if ($user->isAdmin()) {
            return redirect()->route('admin.chat.index')->with('error', 'Chỉ chat với khách hàng.');
        }

        ChatMessage::where('customer_id', $user->id)
            ->where('is_from_admin', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = ChatMessage::with('sender')
            ->where('customer_id', $user->id)
            ->orderBy('created_at')
            ->get();

        $awaitingReply = $messages->isNotEmpty() && ! $messages->last()->is_from_admin;

        return view('admin.chat.show', compact('user', 'messages', 'awaitingReply'));
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return redirect()->route('admin.chat.index')->with('error', 'Chỉ chat với khách hàng.');
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Vui lòng nhập nội dung trả lời.',
        ]);

        ChatMessage::create([
            'customer_id' => $user->id,
            'sender_id' => Auth::id(),
            'body' => trim($data['body']),
            'is_from_admin' => true,
        ]);

        return redirect()->route('admin.chat.show', $user)->with('success', 'Đã gửi trả lời.');
    }
}
