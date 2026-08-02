<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    /** @use HasFactory<\Database\Factories\CommentFactory> */
    use HasFactory;

    protected $fillable = ['diary_id', 'user_id', 'body', 'parent_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function diary()
    {
        return $this->belongsTo(Diary::class);
    }

    /**
     * このコメントを、そのユーザーに閲覧・いいねを許可してよいか？
     * （紐づく日記が閲覧可能かどうかで判定）
     */
    public function isVisibleTo(User $user): bool
    {
        return $this->diary->isVisibleOrOwnedBy($user);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function likers()
    {
        return $this->morphToMany(User::class, 'likeable', 'likes', 'likeable_id', 'user_id')
            ->withTimestamps();
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'root_id', 'id')
                ->whereColumn('id', '<>', 'root_id') // 親本人は除外
                ->orderBy('path'); // 古い順
    }

    // ルート判定 このコメントが「親」かどうか。parent_idがnullなら親
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    // 日記単位でコメントをツリー順で返す
    public function scopeForDiaryThread($q, Diary $diary)
    {
        return $q->where('diary_id', $diary->id)->orderBy('path');
    }

    protected static function booted()
    {
        // 保存前にdepth,root_idを決める
        static::creating(function(Comment $comment) {
            if($comment->parent_id) {
                $parent = Comment::select('id', 'depth', 'root_id')->find($comment->parent_id);
                $comment->depth = ($parent?->depth ?? -1) + 1;
                $comment->root_id = $parent?->root_id ?: $parent?->id;
            } else {
                $comment->depth = 0;
                $comment->root_id = null; // created後に自分のidで上書き
            }
        });

        static::created(function(Comment $comment) {
            $seg = str_pad((string)$comment->id, 10, '0', STR_PAD_LEFT);
            if($comment->parent_id) {
                $parentPath = Comment::whereKey($comment->parent_id)->value('path');
                $comment->path = $parentPath ? ($parentPath . '/' . $seg) : $seg;
                $comment->saveQuietly();

            } else {
                $comment->path = $seg;
                $comment->root_id = $comment->id;
                $comment->saveQuietly();
            }
        });

    }

}
