<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * List paginated notifications for current authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['unread_only', 'status', 'type']);
        $perPage = (int) $request->input('per_page', 15);

        $notifications = $this->notificationService->getUserNotifications($request->user(), $filters, $perPage);
        $unreadCount = $this->notificationService->getUnreadCount($request->user());

        return response()->json([
            'success' => true,
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Get unread notifications count for current user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $unreadCount = $this->notificationService->getUnreadCount($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read successfully.',
            'data' => new NotificationResource($notification),
        ]);
    }

    /**
     * Mark all notifications as read for current user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'success' => true,
            'message' => "Successfully marked {$count} notifications as read.",
            'data' => [
                'marked_count' => $count,
            ],
        ]);
    }

    /**
     * Delete/dismiss a notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->notificationService->destroy($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }
}
