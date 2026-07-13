<?php

namespace App\Models;

use Carbon\TranslatorStrongTypeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;


class Diary extends Model
{
    /** @use HasFactory<\Database\Factories\DiaryFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'artist_id', 'happened_on', 'body', 'is_public'];
    protected $casts = [
        'happened_on' => 'date',
        'is_public' => 'boolean'
    ]; // is_publicの値(0,1)をfalseやtrueに変換

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function artist()
    {
        return $this->belongsTo(Artist::class)->withTrashed();
    }

    public function images()
    {
        return $this->hasMany(DiaryImage::class)->orderBy('id');
    }

    public function coverImage()
    {
        return $this->hasOne(DiaryImage::class)->oldestOfMany();
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /**
     * この日記をそのユーザーがいいね済か？を調べる関数
     */
    public function likedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * 公開日記として、そのユーザーに閲覧・いいねを許可してよいか？
     * （非公開日記、または投稿者にブロックされている場合はfalse）
     */
    public function isVisibleTo(User $user): bool
    {
        return $this->is_public && !$this->user->isBlocking($user);
    }

    /**
     * 自分の日記、または閲覧を許可された公開日記か？
     * （コメント投稿など、非公開でも投稿者本人には許可したい操作向け）
     */
    public function isVisibleOrOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id || $this->isVisibleTo($user);
    }

    public function likers()
    {
        return $this->morphToMany(User::class,  'likeable', 'likes', 'likeable_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Diaryモデルの起動フック。
     * 日記削除前（deleting）のイベントリスナーを登録し、
     * 1) 関連する画像レコードからファイルパスを収集し、
     * 2) Storage（publicディスク）から該当ファイルを物理削除する
     * という前処理をセットアップする。
     *
     * 前提:
     * - 本プロジェクトでは日記は物理削除（SoftDeletesなし）
     * - 画像の保存先は storage/app/public（php artisan storage:link 済）
     */
    protected static function booted(): void
    {
        // 日記削除の直前に、関連画像をストレージから削除する
        static::deleting(function(Diary $diary) {
            // 画像テーブルからファイルパスをまとめて取り出す。
            $paths = $diary->images()->pluck('path')->filter()->values()->all();
            // 見つかったファイルを物理削除（publicディスク想定）
            if(!empty($paths)) {
                Storage::disk('public')->delete($paths);
            }
        });
    }
}
