<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meeting\MarkAttendanceRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Services\MeetingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MarkMeetingAttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MeetingService $meetingService
    ) {}

    /**
     * Mark attendance for participants in a specific meeting.
     */
    public function __invoke(MarkAttendanceRequest $request, Meeting $meeting): JsonResponse
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
}
