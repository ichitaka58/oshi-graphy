<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();

        $conversations = Conversation::forUser($currentUser)
            ->with(['userOne', 'userTwo', 'lastMessage'])
            ->orderByDesc('last_message_at')
            ->get();

        foreach ($conversations as $conversation) {
            // 相手を取得
            $conversation->other_user = $conversation->otherUser($currentUser);
            // 自分の既読日時を取得
            $myReadAt = $conversation->user_one_id === $currentUser->id
                ? $conversation->user_one_read_at
                : $conversation->user_two_read_at;
            // 「last_message_atがあり、かつ自分のread_atが無いか、last_message_atより前」なら未読
            // gt()はCarbon日時比較用メソッド greater then（より大きい）
            $conversation->is_unread = $conversation->last_message_at !== null
                && (is_null($myReadAt) || $conversation->last_message_at->gt($myReadAt));
            
            $conversation->makeHidden(['userOne', 'userTwo']);
        }

        return response()->json([
            'conversations' => $conversations
        ]);
    }
}
