<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use App\Models\Conversation;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index()
    {
        $userId = Auth::id();
        $conversations = Conversation::with(['listing.images', 'buyer', 'seller', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->where(function ($q) use ($userId) {
                $q->where('buyer_id', $userId)->orWhere('seller_id', $userId);
            })
            ->orderByDesc('last_message_at')
            ->paginate(15);

        return view('messages.index', compact('conversations'));
    }

    public function start(Request $request, Listing $listing)
    {
        $data = $request->validate([
            'body' => 'nullable|string|max:1000',
            'offer_amount' => 'nullable|integer|min:10000|max:200000000',
        ]);

        if (empty($data['body']) && empty($data['offer_amount'])) {
            return back()->with('error', 'Nhập tin nhắn hoặc số trả giá.')->withInput();
        }

        $conversation = $this->chat->start(
            $request->user(),
            $listing,
            $data['body'] ?? null,
            isset($data['offer_amount']) ? (int) $data['offer_amount'] : null
        );

        return redirect()->to(route('listings.show', $listing).'#chat-shop')
            ->with('success', ! empty($data['offer_amount']) ? 'Đã gửi trả giá.' : 'Đã gửi tin cho người bán.');
    }

    public function show(Conversation $conversation)
    {
        $userId = Auth::id();
        abort_unless(in_array($userId, [$conversation->buyer_id, $conversation->seller_id], true), 403);

        $conversation->load(['listing.images', 'buyer', 'seller']);
        $messages = $conversation->messages()->with('user')->orderBy('created_at')->get();
        $conversation->messages()->where('user_id', '!=', $userId)->whereNull('read_at')->update(['read_at' => now()]);

        return view('messages.show', compact('conversation', 'messages'));
    }

    public function poll(Request $request, Conversation $conversation)
    {
        $userId = Auth::id();
        abort_unless(in_array($userId, [$conversation->buyer_id, $conversation->seller_id], true), 403);

        $after = $request->integer('after');
        $messages = $conversation->messages()->with('user')
            ->when($after, fn ($q) => $q->where('id', '>', $after))
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'user_name' => $m->user->name,
                'body' => $m->body,
                'offer_amount' => $m->offer_amount,
                'offer_status' => $m->offer_status,
                'mine' => $m->user_id === $userId,
            ]);

        return response()->json(['messages' => $messages]);
    }

    public function reply(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['body' => 'required|string|max:1000']);
        $this->chat->post($conversation, $request->user(), $data['body']);

        if ($request->boolean('inline')) {
            return redirect()->to(route('listings.show', $conversation->listing).'#chat-shop');
        }

        return back();
    }

    public function acceptOffer(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->seller_id === Auth::id(), 403);

        $data = $request->validate([
            'message_id' => 'required|exists:messages,id',
        ]);

        $message = $conversation->messages()->whereKey($data['message_id'])->firstOrFail();
        abort_unless($message->offer_amount && $message->offer_status === 'pending', 403);

        $conversation->messages()->whereNotNull('offer_amount')->update(['offer_status' => 'declined']);
        $message->update(['offer_status' => 'accepted']);
        $conversation->update(['accepted_price' => $message->offer_amount, 'last_message_at' => now()]);
        $this->chat->post(
            $conversation,
            $request->user(),
            'Đã đồng ý giá ' . number_format($message->offer_amount, 0, ',', '.') . ' ₫. Người mua có thể thanh toán theo giá này.'
        );

        return back()->with('success', 'Đã chấp nhận trả giá.');
    }
}
