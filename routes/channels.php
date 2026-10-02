<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{conversation}', function ($user, Conversation $conversation) {
    return in_array((int) $user->id, [(int) $conversation->buyer_id, (int) $conversation->seller_id], true);
});
