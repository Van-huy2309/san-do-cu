<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupportChatController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = Auth::user();
        if ($user->isAdmin()) {
            return redirect()->route('admin.chat.index');
        }

        ChatMessage::where('customer_id', $user->id)
            ->where('is_from_admin', true)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = ChatMessage::with('sender')
            ->where('customer_id', $user->id)
            ->orderBy('created_at')
            ->get();

        return view('account.support', compact('messages'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user->isAdmin()) {
            return redirect()->route('admin.chat.index');
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Vui lòng nhập nội dung tin nhắn.',
        ]);

        ChatMessage::create([
            'customer_id' => $user->id,
            'sender_id' => $user->id,
            'body' => trim($data['body']),
            'is_from_admin' => false,
        ]);

        return redirect()->route('support.index')->with('success', 'Đã gửi tin nhắn cho admin.');
    }
}
