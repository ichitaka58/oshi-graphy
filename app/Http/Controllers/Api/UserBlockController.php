<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserBlockController extends Controller
{
    /**
     * ユーザーをブロックする
     */
    public function store(Request $request, User $user)
    {
        // 自分をブロックできない
        Gate::authorize('block', $user);

        // すでにブロック済みならOK
        if ($request->user()->isBlocking($user)) {
            return response()->json([
                'ok' => true,
                'blocking' => true,
            ]);
        }

        // ブロックしたユーザーをフォローしていたら解除
        if ($request->user()->isFollowing($user)) {
            Follow::where('follower_id', $request->user()->id)
                ->where('followed_id', $user->id)
                ->delete();
        }

        // ブロックしたユーザーにフォローされていたら、解除。
        if ($user->isFollowing($request->user())) {
            Follow::where('follower_id', $user->id)
                ->where('followed_id', $request->user()->id)
                ->delete();
        }

        Block::firstOrCreate([
            'blocker_id' => $request->user()->id,
            'blocked_id' => $user->id,
        ]);

        return response()->json([
            'ok' => true,
            'blocking' => true,
        ]);
    }

    /**
     * ユーザーのブロックを解除する
     */
    public function destroy(Request $request, User $user)
    {
        // ブロック解除可能か（=自分がブロックしていたらOK）
        Gate::authorize('unBlock', $user);

        Block::where('blocker_id', $request->user()->id)
            ->where('blocked_id', $user->id)
            ->delete();

        return response()->json([
            'ok' => true,
            'blocking' => false,
        ]);
    }

    /**
     * ブロックしているユーザーの一覧をページネーションで取得
     */
    public function blocks(Request $request)
    {
        $blocks = $request->user()->blocks()
            ->orderByPivot('created_at', 'desc')->paginate(10)->withQueryString();

        return response()->json([
            'blocks' => $blocks,
        ]);
    }
}
