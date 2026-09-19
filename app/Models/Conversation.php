<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'last_message_at',
        'user_one_read_at',
        'user_two_read_at'
    ];

    /**
     * 文字列をdatetime型に変換し、比較できるようにする
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'user_one_read_at' => 'datetime',
            'user_two_read_at' => 'datetime',
        ];
    }

    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        // 最も新しい関連レコード1件を取得、デフォルトは主キー(id)が最大のものが最新として選ばれる
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // 相手ユーザーを取得するヘルパーメソッド
    public function otherUser(User $currentUser)
    {
        return $this->user_one_id === $currentUser->id ? $this->userTwo : $this->userOne;
    }

    // クエリスコープ 使う時はConversation::forUser($user)のようにscopeを省略して使うことができる
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id);
        });
    }

    // 自分の既読日時のカラムを取得するヘルパーメソッド
    public function readAtColumnFor(User $user)
    {
        return $this->user_one_id === $user->id
            ? 'user_one_read_at'
            : 'user_two_read_at';
    }

    // 自分の既読日時の値を取得するヘルパーメソッド
    public function readAtFor(User $user)
    {
        $column = $this->readAtColumnFor($user);
        return $this->$column;
    }
}
