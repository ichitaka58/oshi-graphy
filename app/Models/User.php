<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
    ];
    // $castsは特定の型に自動変換する。is_adminを数値0or1からtrue/falseへ

    protected $fillable = [
        'name',
        'email',
        'password',
        'icon_path',
        'profile',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function diaries()
    {
        return $this->hasMany(Diary::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function followings()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id')->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id')->withTimestamps();
    }

    public function isFollowing(User $other): bool
    {
        if($this->id === $other->id) return false;
        return $this->followings()->whereKey($other->id)->exists();

    }

    public function blocks()
    {
        return $this->belongsToMany(User::class, 'blocks', 'blocker_id', 'blocked_id');
    }

    public function blockedBy()
    {
        return $this->belongsToMany(User::class, 'blocks', 'blocked_id', 'blocker_id');
    }

    public function isBlocking(User $other): bool
    {
        if($this->id === $other->id) return false;
        return $this->blocks()->whereKey($other->id)->exists();
    }

    protected $appends = ['icon_url'];

    public function getIconUrlAttribute(): string
    {
        return $this->icon_path
            ? asset('storage/'.$this->icon_path)
            : asset('images/icon_placeholder.png');
    }

    /**
     * Userモデルの起動フック。
     * ユーザー削除前（deleting）のイベントリスナーを登録し、
     * 1) ユーザーのアイコンファイルを削除し、
     * 2) 所有する日記を Eloquent 経由で chunk 削除（→ Diary::deleting が発火し写真も物理削除）
     * する前処理をセットアップする。
     */
    protected static function booted(): void
    {
        static::deleting(function(User $user) {

            // ユーザーアイコンの物理削除
            if(!empty($user->icon_path)) {
                Storage::disk('public')->delete($user->icon_path);
            }

            // chunkById:ID順に100件ずつとりだして処理する関数
            $user->diaries()->select('id')->chunkById(100, function($chunk){
                $chunk->each->delete(); // それぞれを削除
            });
        });
    }
}
