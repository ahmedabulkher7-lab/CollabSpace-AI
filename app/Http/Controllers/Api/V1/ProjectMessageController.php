<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Requests\Chat\UpdateMessageRequest;
use App\Http\Resources\ProjectMessageResource;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Services\ChatService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectMessageController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ChatService $chatService
    ) {}

    /**
     * Get paginated messages for a project channel.
     */
    public function index(Project $project, Request $request): JsonResponse
    {
        Gate::authorize('viewAny', [ProjectMessage::class, $project]);

        $messages = $this->chatService->getProjectMessages(
            user: $request->user(),
            project: $project,
            filters: $request->only(['parent_id', 'root_only', 'search']),
            perPage: (int) $request->input('per_page', 30)
        );

        return $this->successResponse(
            ProjectMessageResource::collection($messages)->response()->getData(true),
            'Project messages retrieved successfully.'
        );
    }

    /**
     * Send a new message in project channel.
     */
    public function store(SendMessageRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('send', [ProjectMessage::class, $project]);

        $message = $this->chatService->sendMessage(
            user: $request->user(),
            project: $project,
            data: $request->validated()
        );

        return $this->successResponse(
            new ProjectMessageResource($message),
            'Message sent successfully.',
            201
        );
    }

    /**
     * Edit an existing message.
     */
    public function update(UpdateMessageRequest $request, ProjectMessage $message): JsonResponse
    {
        Gate::authorize('update', $message);

        $updatedMessage = $this->chatService->updateMessage(
            user: $request->user(),
            message: $message,
            newText: $request->validated('message')
        );

        return $this->successResponse(
            new ProjectMessageResource($updatedMessage),
            'Message edited successfully.'
        );
    }

    /**
     * Delete a message (soft delete).
     */
    public function destroy(ProjectMessage $message): JsonResponse
    {
        Gate::authorize('delete', $message);

        $this->chatService->deleteMessage(
            user: request()->user(),
            message: $message
        );

        return $this->successResponse(
            null,
            'Message deleted successfully.'
        );
    }

    /**
     * Mark message(s) in project as read.
     */
    public function markRead(Project $project, Request $request): JsonResponse
    {
        Gate::authorize('viewAny', [ProjectMessage::class, $project]);

        $markedCount = $this->chatService->markAsRead(
            user: $request->user(),
            project: $project,
            messageId: $request->input('message_id') ? (int) $request->input('message_id') : null
        );

        return $this->successResponse([
            'marked_count' => $markedCount,
        ], 'Messages marked as read.');
    }

    /**
     * Get count of unread messages for user in project channel.
     */
    public function unreadCount(Project $project, Request $request): JsonResponse
    {
        Gate::authorize('viewAny', [ProjectMessage::class, $project]);

        $count = $this->chatService->getUnreadCount(
            user: $request->user(),
            project: $project
        );

        return $this->successResponse([
            'project_id' => $project->id,
            'unread_count' => $count,
        ], 'Unread messages count retrieved.');
    }
}
