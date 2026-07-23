<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->latest()->paginate(20);

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    // 通知の未読件数だけを返す
    public function unreadCount(Request $request)
    {
        // return ['count' => $request->user()->unreadNotifications()->count()];
        // count()の結果を配列で返す→LaravelがJSONにして返却({"count":N})
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    // 単一の通知を既読にする
    public function markRead(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        if (is_null($n->read_at)) $n->markAsRead();
        // 未読ならmarkAsRead()->read_atに現在時刻が入る。

        return response()->json([
            'ok' => true,
        ]);
    }

    // 未読通知を一括で既読にする
    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
        ]);
    }

    // 通知の削除
    public function destroy(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    // 既読通知を未読に戻す
    public function markUnread(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->update(['read_at' => null]);

        return response()->json([
            'ok' => true,
        ]);
    }
}
