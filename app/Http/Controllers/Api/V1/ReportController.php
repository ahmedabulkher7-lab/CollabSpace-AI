<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Get Project Reports (active, delayed, completed, completion rates).
     */
    public function projects(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'priority']);
        $report = $this->reportService->getProjectReports($request->user(), $filters);

        return $this->successResponse($report, 'Project reports retrieved successfully.');
    }

    /**
     * Get Task Reports (completion rate, on-time delivery, priority & status breakdowns).
     */
    public function tasks(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'team_id', 'assigned_to']);
        $report = $this->reportService->getTaskReports($request->user(), $filters);

        return $this->successResponse($report, 'Task reports retrieved successfully.');
    }

    /**
     * Get Team Performance Reports and workload distribution.
     */
    public function teams(Request $request): JsonResponse
    {
        $filters = $request->only(['team_id']);
        $report = $this->reportService->getTeamReports($request->user(), $filters);

        return $this->successResponse($report, 'Team performance reports retrieved successfully.');
    }

    /**
     * Get User Productivity and performance report.
     */
    public function users(Request $request, ?int $id = null): JsonResponse
    {
        $report = $this->reportService->getUserReports($request->user(), $id);

        return $this->successResponse($report, 'User productivity reports retrieved successfully.');
    }
}
