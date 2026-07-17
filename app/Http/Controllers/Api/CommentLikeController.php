<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentLikeController extends Controller
{
    public function store(Request $request, Comment $comment)
    {
        abort_unless($comment->isVisibleTo($request->user()), 403);

        $like = $comment->likes()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'ok' => true,
            'liked' => true,
            'count' => $comment->likes()->count(),
        ], 201);
    }

    public function destroy(Request $request, Comment $comment)
    {
        abort_unless($comment->isVisibleTo($request->user()), 403);

        $like = $comment->likes()->where('user_id', $request->user()->id)->first();
        if ($like) {
            $like->delete();
        }

        return response()->json([
            'ok' => true,
            'liked' => false,
            'count' => $comment->likes()->count(),
        ]);
    }

    public function commentLikers(Request $request, Comment $comment)
    {
        // コメントオーナーとログインユーザーが別なら403を返す
        // コメントオーナーしかいいねしたユーザーを見られない
        abort_unless($comment->user_id === $request->user()->id, 403);
        $likers = $comment->likers()
            ->orderByPivot('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $comment->load('diary.user');

        return response()->json([
            'comment' => $comment,
            'likers' => $likers,
        ]);
    }
}
