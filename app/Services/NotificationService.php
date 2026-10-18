<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
    /**
     * Get paginated notifications for the given user.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<DatabaseNotification>
     */
    public function getUserNotifications(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $user->notifications();

        // Filter unread only
        if (! empty($filters['unread_only']) || (isset($filters['status']) && $filters['status'] === 'unread')) {
            $query->whereNull('read_at');
        } elseif (isset($filters['status']) && $filters['status'] === 'read') {
            $query->whereNotNull('read_at');
        }

        // Filter by type
        if (! empty($filters['type'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('type', 'like', "%{$filters['type']}%")
                    ->orWhere('data->type', $filters['type']);
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get the count of unread notifications for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(User $user, string $notificationId): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->findOrFail($notificationId);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $notification;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user): int
    {
        $count = $user->unreadNotifications()->count();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return $count;
    }

    /**
     * Delete/dismiss a notification.
     */
    public function destroy(User $user, string $notificationId): bool
    {
        $notification = $user->notifications()->findOrFail($notificationId);

        return (bool) $notification->delete();
    }
}
