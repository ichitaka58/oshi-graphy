<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;
use App\Models\Comment;

class CommentLiked extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public User $actor,
        public Comment $comment,
        public string $excerpt
    )
    {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'like',
            'target' => 'comment',
            'comment_id' => $this->comment->id,
            'diary_id' => $this->comment->diary_id ?? null,
            'actor_user_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'message' => "{$this->actor->name}さんがあなたのコメント「{$this->excerpt}」にいいねしました。",
        ];
    }


    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
