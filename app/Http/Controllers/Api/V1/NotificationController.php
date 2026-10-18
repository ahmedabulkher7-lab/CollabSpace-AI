<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

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
            'message' => 'Notifications retrieved successfully.',
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

        return $this->successResponse([
            'unread_count' => $unreadCount,
        ], 'Unread count retrieved successfully.');
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($request->user(), $id);

        return $this->successResponse(
            new NotificationResource($notification),
            'Notification marked as read successfully.'
        );
    }

    /**
     * Mark all notifications as read for current user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return $this->successResponse([
            'marked_count' => $count,
        ], "Successfully marked {$count} notifications as read.");
    }

    /**
     * Delete/dismiss a notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->notificationService->destroy($request->user(), $id);

        return $this->successResponse(
            null,
            'Notification deleted successfully.'
        );
    }
}
