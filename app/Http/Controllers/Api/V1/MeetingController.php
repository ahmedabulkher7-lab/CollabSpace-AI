<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meeting\CreateTasksFromSummaryRequest;
use App\Http\Requests\Meeting\GenerateSummaryRequest;
use App\Http\Requests\Meeting\MarkAttendanceRequest;
use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingRequest;
use App\Http\Requests\Meeting\UpdateSummaryRequest;
use App\Http\Resources\MeetingResource;
use App\Http\Resources\MeetingSummaryResource;
use App\Http\Resources\TaskResource;
use App\Models\Meeting;
use App\Services\MeetingService;
use App\Services\MeetingSummaryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeetingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MeetingService $meetingService,
        protected MeetingSummaryService $meetingSummaryService
    ) {}

    /**
     * Display a listing of meetings with filters.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Meeting::class);

        $meetings = $this->meetingService->getAll(
            user: $request->user(),
            filters: $request->only(['search', 'project_id', 'status', 'meeting_date', 'time_filter']),
            perPage: (int) $request->input('per_page', 15)
        );

        return $this->successResponse(
            MeetingResource::collection($meetings)->response()->getData(true),
            'Meetings retrieved successfully.'
        );
    }

    /**
     * Store a newly created meeting.
     */
    public function store(StoreMeetingRequest $request): JsonResponse
    {
        Gate::authorize('create', Meeting::class);

        $meeting = $this->meetingService->create(
            user: $request->user(),
            data: $request->validated()
        );

        return $this->successResponse(
            new MeetingResource($meeting),
            'Meeting scheduled successfully.',
            201
        );
    }

    /**
     * Display the specified meeting.
     */
    public function show(Meeting $meeting): JsonResponse
    {
        Gate::authorize('view', $meeting);

        $meeting->load(['project', 'creator', 'participants', 'summary']);

        return $this->successResponse(
            new MeetingResource($meeting),
            'Meeting details retrieved successfully.'
        );
    }

    /**
     * Update the specified meeting.
     */
    public function update(UpdateMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('update', $meeting);

        $updatedMeeting = $this->meetingService->update(
            user: $request->user(),
            meeting: $meeting,
            data: $request->validated()
        );

        return $this->successResponse(
            new MeetingResource($updatedMeeting),
            'Meeting updated successfully.'
        );
    }

    /**
     * Remove the specified meeting.
     */
    public function destroy(Meeting $meeting): JsonResponse
    {
        Gate::authorize('delete', $meeting);

        $this->meetingService->delete(
            user: request()->user(),
            meeting: $meeting
        );

        return $this->successResponse(
            null,
            'Meeting deleted successfully.'
        );
    }

    /**
     * Mark attendance for participants.
     */
    public function markAttendance(MarkAttendanceRequest $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('update', $meeting);

        $updatedMeeting = $this->meetingService->markAttendance(
            user: $request->user(),
            meeting: $meeting,
            attendanceList: $request->validated('attendance')
        );

        return $this->successResponse(
            new MeetingResource($updatedMeeting),
            'Attendance marked successfully.'
        );
    }

    /**
     * Generate AI-powered summary for meeting.
     */
    public function generateSummary(GenerateSummaryRequest $request, Meeting $meeting): JsonResponse
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
     * View summary for meeting.
     */
    public function showSummary(Meeting $meeting): JsonResponse
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
     * Manually update summary for meeting.
     */
    public function updateSummary(UpdateSummaryRequest $request, Meeting $meeting): JsonResponse
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
    public function createTasksFromSummary(CreateTasksFromSummaryRequest $request, Meeting $meeting): JsonResponse
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
