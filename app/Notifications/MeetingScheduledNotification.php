<?php

namespace App\Notifications;

use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MeetingScheduledNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Meeting $meeting,
        public ?Project $project,
        public User $host
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
        $startTime = is_string($this->meeting->start_time)
            ? $this->meeting->start_time
            : ($this->meeting->start_time instanceof \DateTimeInterface ? $this->meeting->start_time->format('H:i') : (string) $this->meeting->start_time);

        return [
            'type' => 'meeting_scheduled',
            'title' => 'Meeting Scheduled',
            'message' => "You have been invited to meeting '{$this->meeting->title}' scheduled for {$startTime}.",
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
            'project_id' => $this->project?->id,
            'project_name' => $this->project?->name,
            'host_id' => $this->host->id,
            'host_name' => $this->host->name,
            'start_time' => $startTime,
        ];
    }
}
