<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;


class UserFollowController extends Controller
{
    /**
     * ユーザーフォロー情報を保存
     */
    public function store(Request $request, User $user)
    {
        Gate::authorize('follow', $user);

        // すでにフォロー済みならOKで返す
        if ($request->user()->isFollowing($user)) {
            return response()->json([
                'ok' => true,
                'following' => true,
                'followings_count' => $user->followings()->count(),
                'followers_count' => $user->followers()->count(),
            ]);
        }

        Follow::firstOrCreate([
            'follower_id' => $request->user()->id,
            'followed_id' => $user->id,
        ]);

        return response()->json([
            'ok' => true,
            'following' => true,
            'followings_count' => $user->followings()->count(),
            'followers_count' => $user->followers()->count(),
        ]);
    }

    /**
     * フォロー解除
     */
    public function destroy(Request $request, User $user)
    {
        Gate::authorize('unfollow', $user);

        Follow::where('follower_id', $request->user()->id)
            ->where('followed_id', $user->id)
            ->delete();

        return response()->json([
            'ok' => true,
            'following' => false,
            'followings_count' => $user->followings()->count(),
            'followers_count' => $user->followers()->count(),
        ]);
    }

    /**
     * $userのフォロワー一覧をページネーションで取得
     */
    public function followers(User $user)
    {
        $followers = $user->followers()
            ->orderByPivot('created_at', 'desc')->paginate(10)->withQueryString();

        return response()->json([
            'followers' => $followers,
        ]);
    }

    /**
     * $userがフォローしている人の一覧をページネーションで取得
     */
    public function followings(User $user)
    {
        $followings = $user->followings()
            ->orderByPivot('created_at', 'desc')->paginate(10)->withQueryString();

        return response()->json([
            'followings' => $followings,
        ]);
    }
}
