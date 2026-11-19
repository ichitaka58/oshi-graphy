<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;
use App\Models\Diary;


class DiaryLiked extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */

    public function __construct(
        public User $actor,
        public Diary $diary,
        public string $excerpt
    )
    { }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'like',
            'target' => 'diary',
            'diary_id' => $this->diary->id,
            'actor_user_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'message' => "{$this->actor->name}さんがあなたの日記「{$this->excerpt}」にいいねしました。",
        ];
    }

}
