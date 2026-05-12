<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Diary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(Request $request, Diary $diary)
    {
        Gate::authorize('create', Comment::class);

        if (!$diary->is_public && auth()->id() !== $diary->user_id) {
            return abort(403);
        }

        $validated = $request->validateWithBag('comment', [
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $diary->comments()->create([
            'user_id' => auth()->id(),
            'body' => $validated['body'],
            'parent_id' => null,
        ]);

        return response()->json([
            'message' => 'Comment created successfully'
        ], 201);

    }

    public function reply(Request $request, Diary $diary)
    {
        Gate::authorize('reply', Comment::class);

        if (!$diary->is_public && auth()->id() !== $diary->user_id) {
            return abort(403);
        }

        $validated = $request->validateWithBag('reply', [
            'body' => ['required', 'string', 'max:2000'],
            // exists:comments,id コメントテーブルのidに存在する
            'parent_id' => ['required', 'integer', 'exists:comments,id'],
        ]);

        $parent = Comment::findOrFail($validated['parent_id']);
        if ($parent->diary_id !== $diary->id) {
            abort(422, '親コメントがこの日記のものではありません。');
        }

        $diary->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'parent_id' => $validated['parent_id'],
        ]);

        return response()->json([
            'message' => 'Reply comment created successfully'
        ], 201);
    }

    public function destroy(Comment $comment)
    {
        Gate::authorize('delete', $comment);

        $isReply = $comment->parent_id !== null;

        $comment->delete();

        return response()->json([
            'isReply' => $isReply,
        ]);
    }
}
