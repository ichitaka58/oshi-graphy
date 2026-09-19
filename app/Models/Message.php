<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['sender_id', 'body'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    protected static function booted(): void
    {
        // メッセージを作ると同時に処理すること
        static::created(function (Message $message) {
            $conversation = $message->conversation;
            // 会話のlast_message_atと送信者側のread_atをメッセージのcreated_atを入れる
            $conversation->update([
                'last_message_at' => $message->created_at,
                $conversation->readAtColumnFor($message->sender) => $message->created_at,
            ]);
        });
    }
}
