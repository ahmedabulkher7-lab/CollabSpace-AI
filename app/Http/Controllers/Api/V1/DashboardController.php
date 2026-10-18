<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Get aggregated workspace statistics, task status counts, and recent activity.
     */
    public function index(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getDashboardStats($request->user());

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
