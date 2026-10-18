<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meeting\CreateTasksFromSummaryRequest;
use App\Http\Requests\Meeting\GenerateSummaryRequest;
use App\Http\Requests\Meeting\UpdateSummaryRequest;
use App\Http\Resources\MeetingSummaryResource;
use App\Http\Resources\TaskResource;
use App\Models\Meeting;
use App\Services\MeetingSummaryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MeetingSummaryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MeetingSummaryService $meetingSummaryService
    ) {}

    /**
     * View summary for a specific meeting.
     */
    public function show(Meeting $meeting): JsonResponse
    {
        Gate::authorize('view', $meeting);

        if (! $meeting->summary) {
            return $this->errorResponse('No summary found for this meeting.', 404);
        }

        return $this->successResponse(
            new MeetingSummaryResource($meeting->summary),
            'Meeting summary retrieved successfully.'
        );
    }

    /**
     * Generate AI-powered summary for meeting.
     */
    public function generate(GenerateSummaryRequest $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('update', $meeting);

        $summary = $this->meetingSummaryService->generate(
            user: $request->user(),
            meeting: $meeting,
            transcript: $request->input('transcript')
        );

        return $this->successResponse(
            new MeetingSummaryResource($summary),
            'AI meeting summary generated successfully.'
        );
    }

    /**
     * Manually update summary for meeting.
     */
    public function update(UpdateSummaryRequest $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('update', $meeting);

        $summary = $this->meetingSummaryService->update(
            user: $request->user(),
            meeting: $meeting,
            data: $request->validated()
        );

        return $this->successResponse(
            new MeetingSummaryResource($summary),
            'Meeting summary updated successfully.'
        );
    }

    /**
     * Convert AI action items from summary into official Kanban tasks.
     */
    public function createTasks(CreateTasksFromSummaryRequest $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('update', $meeting);

        $tasks = $this->meetingSummaryService->createTasksFromSummary(
            user: $request->user(),
            meeting: $meeting,
            options: $request->validated()
        );

        return $this->successResponse([
            'tasks_count' => $tasks->count(),
            'tasks' => TaskResource::collection($tasks),
        ], "{$tasks->count()} tasks successfully created on the Kanban board from meeting action items.", 201);
    }
}
