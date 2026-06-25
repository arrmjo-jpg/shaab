<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * قناة محادثة الشات — التفويض بالعضوية الفعلية (row-level)، لا مجرّد auth()->check().
 * يُصرّح فقط لمن هو عضو في conversation_participants لهذه المحادثة.
 */
Broadcast::channel('chat.conversation.{conversation}', function (User $user, int $conversation) {
    $c = Conversation::find($conversation);

    return $c !== null && $c->hasParticipant($user->id);
});
