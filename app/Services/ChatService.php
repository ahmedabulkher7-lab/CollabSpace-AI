<?php

namespace App\Services;

use App\Enums\AuditLogCategory;
use App\Events\MessageSent;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use App\Notifications\MentionedInMessageNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ChatService
{
    /**
     * Get paginated messages for a project channel.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<ProjectMessage>
     */
    public function getProjectMessages(User $user, Project $project, array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        $query = ProjectMessage::query()
            ->where('project_id', $project->id)
            ->with(['user', 'parent.user', 'attachment', 'mentions', 'readers']);

        if (! empty($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        } elseif (isset($filters['root_only']) && $filters['root_only']) {
            $query->whereNull('parent_id');
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('message', 'like', "%{$search}%");
        }

        // Return latest messages
        return $query->latest('created_at')->paginate($perPage);
    }

    /**
     * Send a new message in project channel.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendMessage(User $user, Project $project, array $data): ProjectMessage
    {
        return DB::transaction(function () use ($user, $project, $data) {
            $mentions = $data['mentions'] ?? [];
            unset($data['mentions']);

            $data['project_id'] = $project->id;
            $data['user_id'] = $user->id;

            /** @var ProjectMessage $message */
            $message = ProjectMessage::create($data);

            if (! empty($mentions)) {
                $message->mentions()->sync($mentions);

                $mentionedUsers = User::whereIn('id', $mentions)
                    ->where('id', '!=', $user->id)
                    ->get();

                foreach ($mentionedUsers as $mentionedUser) {
                    $mentionedUser->notify(new MentionedInMessageNotification($message, $project, $user));
                }
            }

            // Automatically mark as read by sender
            $message->readers()->attach($user->id, [
                'read_at' => Carbon::now(),
            ]);

            $loadedMessage = $message->load(['user', 'parent.user', 'attachment', 'mentions', 'readers']);

            // Broadcast event over WebSocket / Reverb
            try {
                broadcast(new MessageSent($loadedMessage))->toOthers();
            } catch (\Throwable) {
                // Keep resilient if broadcasting daemon is not running in background
            }

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Chat->value,
                'action' => 'message_sent',
                'description' => "Message sent in project '{$project->name}'.",
                'payload' => [
                    'message_id' => $message->id,
                    'project_id' => $project->id,
                    'has_attachment' => ! empty($message->file_attachment_id),
                    'is_reply' => ! empty($message->parent_id),
                ],
            ]);

            return $loadedMessage;
        });
    }

    /**
     * Edit message text.
     */
    public function updateMessage(User $user, ProjectMessage $message, string $newText): ProjectMessage
    {
        return DB::transaction(function () use ($user, $message, $newText) {
            $message->update([
                'message' => $newText,
                'is_edited' => true,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Chat->value,
                'action' => 'message_edited',
                'description' => "Message ID {$message->id} edited.",
                'payload' => ['message_id' => $message->id],
            ]);

            return $message->fresh(['user', 'parent.user', 'attachment', 'mentions', 'readers']);
        });
    }

    /**
     * Delete a message (soft delete).
     */
    public function deleteMessage(User $user, ProjectMessage $message): void
    {
        DB::transaction(function () use ($user, $message) {
            $messageId = $message->id;
            $projectId = $message->project_id;

            $message->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Chat->value,
                'action' => 'message_deleted',
                'description' => "Message ID {$messageId} deleted from project ID {$projectId}.",
                'payload' => ['message_id' => $messageId],
            ]);
        });
    }

    /**
     * Mark message(s) as read.
     */
    public function markAsRead(User $user, Project $project, ?int $messageId = null): int
    {
        return DB::transaction(function () use ($user, $project, $messageId) {
            $now = Carbon::now();

            if ($messageId) {
                $message = ProjectMessage::where('project_id', $project->id)->findOrFail($messageId);
                $message->readers()->syncWithoutDetaching([
                    $user->id => ['read_at' => $now],
                ]);

                return 1;
            }

            // Mark all unread messages in project
            $unreadMessages = ProjectMessage::query()
                ->where('project_id', $project->id)
                ->where('user_id', '!=', $user->id)
                ->whereDoesntHave('readers', fn ($q) => $q->where('users.id', $user->id))
                ->pluck('id');

            foreach ($unreadMessages as $id) {
                DB::table('message_reads')->insertOrIgnore([
                    'message_id' => $id,
                    'user_id' => $user->id,
                    'read_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $unreadMessages->count();
        });
    }

    /**
     * Get count of unread messages for user in project channel.
     */
    public function getUnreadCount(User $user, Project $project): int
    {
        return ProjectMessage::query()
            ->where('project_id', $project->id)
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('readers', fn ($q) => $q->where('users.id', $user->id))
            ->count();
    }
}
