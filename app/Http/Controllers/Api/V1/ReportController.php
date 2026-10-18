<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
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

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get Task Reports (completion rate, on-time delivery, priority & status breakdowns).
     */
    public function tasks(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'team_id', 'assigned_to']);
        $report = $this->reportService->getTaskReports($request->user(), $filters);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get Team Performance Reports and workload distribution.
     */
    public function teams(Request $request): JsonResponse
    {
        $filters = $request->only(['team_id']);
        $report = $this->reportService->getTeamReports($request->user(), $filters);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get User Productivity and performance report.
     */
    public function users(Request $request, ?int $id = null): JsonResponse
    {
        $report = $this->reportService->getUserReports($request->user(), $id);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}
