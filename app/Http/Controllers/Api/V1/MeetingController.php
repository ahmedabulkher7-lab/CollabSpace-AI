<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Services\MeetingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeetingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MeetingService $meetingService
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
}
