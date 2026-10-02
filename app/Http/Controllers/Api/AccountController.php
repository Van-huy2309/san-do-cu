<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Listing;
use App\Services\ChatService;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function favorites(Request $request)
    {
        $listings = Listing::public()
            ->whereIn('id', $request->user()->favorites()->pluck('listing_id'))
            ->with(['images', 'brand', 'category', 'origin'])
            ->latest('published_at')
            ->get();

        return response()->json(['data' => $listings->map(fn (Listing $l) => [
            'id' => $l->id,
            'slug' => $l->slug,
            'title' => $l->title,
            'formatted_price' => $l->formattedPrice(),
            'cover' => $l->coverUrl(),
            'city' => $l->city,
        ])]);
    }

    public function toggleFavorite(Request $request, Listing $listing)
    {
        $existing = Favorite::where('user_id', $request->user()->id)->where('listing_id', $listing->id)->first();
        if ($existing) {
            $existing->delete();

            return response()->json(['favorited' => false]);
        }
        Favorite::create(['user_id' => $request->user()->id, 'listing_id' => $listing->id]);

        return response()->json(['favorited' => true]);
    }

    public function conversations(Request $request)
    {
        $userId = $request->user()->id;
        $rows = Conversation::with(['listing.images', 'buyer', 'seller', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->where(fn ($q) => $q->where('buyer_id', $userId)->orWhere('seller_id', $userId))
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json(['data' => $rows->map(fn (Conversation $c) => [
            'id' => $c->id,
            'title' => $c->listing->title,
            'other' => $c->otherParty($userId)->name,
            'last' => $c->messages->first()?->body,
        ])]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        abort_unless(in_array($request->user()->id, [$conversation->buyer_id, $conversation->seller_id], true), 403);
        $messages = $conversation->messages()->with('user')->orderBy('created_at')->get();

        return response()->json(['data' => $messages->map(fn ($m) => [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'user_name' => $m->user->name,
            'body' => $m->body,
            'mine' => $m->user_id === $request->user()->id,
        ])]);
    }

    public function reply(Request $request, Conversation $conversation, ChatService $chat)
    {
        $data = $request->validate(['body' => 'required|string|max:1000']);
        $message = $chat->post($conversation, $request->user(), $data['body']);

        return response()->json([
            'id' => $message->id,
            'body' => $message->body,
            'mine' => true,
        ]);
    }

    public function startChat(Request $request, Listing $listing, ChatService $chat)
    {
        $data = $request->validate(['body' => 'required|string|max:1000']);
        $conversation = $chat->start($request->user(), $listing, $data['body'], null);

        return response()->json(['conversation_id' => $conversation->id]);
    }
}
