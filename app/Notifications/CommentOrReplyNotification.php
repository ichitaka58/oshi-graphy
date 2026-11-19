<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommentOrReplyNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $diaryId,
        public int $commentId,
        public ?int $actorUserId,
        public string $actorName,
        public bool $isReply,
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

    /**
     * Store the notification data in the database.
     */
    public function toDatabase($notifiable): array
    {
        return [
            'type' => $this->isReply ? 'reply' :'comment',
            'diary_id' => $this->diaryId,
            'comment_id' => $this->commentId,
            'actor_user_id' => $this->actorUserId,
            'actor_name' => $this->actorName,
            'message' => $this->isReply
                        ? "{$this->actorName}さんがあなたのコメント「{$this->excerpt}」に返信しました。"
                        : "{$this->actorName}さんがあなたの日記「{$this->excerpt}」にコメントしました。",
        ];
    }

}
