<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    // DM（会話）一覧の取得
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
            // 自分の既読日時を取得 readAtFor()はConversationモデルに定義したヘルパーメソッド
            $myReadAt = $conversation->readAtFor($currentUser);
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

    // 会話のメッセージ一覧を取得
    public function show(Request $request, Conversation $conversation)
    {
        Gate::authorize('view', $conversation);

        // 相手を取得
        $conversation->other_user = $conversation->otherUser($request->user());
        $conversation->makeHidden(['userOne', 'userTwo']);
        $messages = $conversation->messages()->latest()->latest('id')->paginate(10);

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    // 自分のread_atを更新 既読にする
    public function read(Request $request, Conversation $conversation)
    {
        Gate::authorize('view', $conversation);

        $conversation->update([
            $conversation->readAtColumnFor($request->user()) => now(),
        ]);

        // ステータスコード204 No Contentを返す
        return response()->noContent();
    }

    // 未読の会話数を取得
    public function unreadCount(Request $request)
    {
        $unreadCount = Conversation::unreadFor($request->user())->count();

        return response()->json([
            'unread_count' => $unreadCount,
        ]);
    }
}
