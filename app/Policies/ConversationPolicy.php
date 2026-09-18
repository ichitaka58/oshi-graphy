<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * 会話を閲覧できるか（当事者であり、どちらからもブロックしていない）
     */
    public function view(User $user, Conversation $conversation): bool
    {
        // ①当事者でなければ弾く
        if($conversation->user_one_id !== $user->id && $conversation->user_two_id !== $user->id) return false;
        // ②ここからは当事者確定なので、otherUser()が正しく相手を返す
        $otherUser = $conversation->otherUser($user);
        // どちらからがブロックしていれば弾く
        if($user->isBlocking($otherUser) || $otherUser->isBlocking($user)) return false;
        return true;
    }
}
