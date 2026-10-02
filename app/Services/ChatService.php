<?php

namespace App\Services;

use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;

class ChatService
{
    public function start(User $buyer, Listing $listing, ?string $body, ?int $offerAmount): Conversation
    {
        abort_unless($listing->isActive(), 404);
        abort_if($listing->seller_id === $buyer->id, 403, 'Không thể chat với chính mình.');
        abort_if($body === null && ! $offerAmount, 422);

        $conversation = Conversation::firstOrCreate(
            ['listing_id' => $listing->id, 'buyer_id' => $buyer->id],
            ['seller_id' => $listing->seller_id, 'last_message_at' => now()]
        );

        $text = $offerAmount
            ? ('Đề nghị giá ' . number_format($offerAmount, 0, ',', '.') . ' ₫' . ($body ? "\n" . $body : ''))
            : (string) $body;

        $this->post($conversation, $buyer, $text, $offerAmount);

        return $conversation;
    }

    public function post(Conversation $conversation, User $user, string $body, ?int $offerAmount = null): Message
    {
        abort_unless(in_array($user->id, [$conversation->buyer_id, $conversation->seller_id], true), 403);

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $body,
            'offer_amount' => $offerAmount,
            'offer_status' => $offerAmount ? 'pending' : null,
        ]);
        $conversation->update(['last_message_at' => now()]);
        try {
            broadcast(new MessageCreated($message->load('user')));
        } catch (\Throwable) {
        }

        return $message;
    }
}
