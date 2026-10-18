<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MentionedInMessageNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ProjectMessage $messageModel,
        public Project $project,
        public User $sender
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'chat_mention',
            'title' => 'Mentioned in Chat',
            'message' => "{$this->sender->name} mentioned you in project '{$this->project->name}'.",
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'message_id' => $this->messageModel->id,
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
        ];
    }
}
