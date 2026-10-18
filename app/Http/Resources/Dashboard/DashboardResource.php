<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator $activeProjects */
        $activeProjects = $this->resource['active_projects'];

        /** @var LengthAwarePaginator $tasks */
        $tasks = $this->resource['tasks'];

        /** @var LengthAwarePaginator $teams */
        $teams = $this->resource['teams'];

        /** @var LengthAwarePaginator $recentFiles */
        $recentFiles = $this->resource['recent_files'];

        /** @var LengthAwarePaginator $recentActivity */
        $recentActivity = $this->resource['recent_activity'];

        return [
            'overview' => $this->resource['overview'],
            'project_overview' => $this->resource['project_overview'],
            'active_projects' => [
                'items' => DashboardProjectResource::collection($activeProjects->items()),
                'pagination' => [
                    'current_page' => $activeProjects->currentPage(),
                    'per_page' => $activeProjects->perPage(),
                    'total' => $activeProjects->total(),
                ],
            ],
            'tasks' => [
                'items' => DashboardTaskResource::collection($tasks->items()),
                'pagination' => [
                    'current_page' => $tasks->currentPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                ],
            ],
            'teams' => [
                'items' => DashboardTeamResource::collection($teams->items()),
                'pagination' => [
                    'current_page' => $teams->currentPage(),
                    'per_page' => $teams->perPage(),
                    'total' => $teams->total(),
                ],
            ],
            'upcoming_meetings' => [
                'items' => DashboardMeetingResource::collection($this->resource['upcoming_meetings']),
            ],
            'recent_files' => [
                'items' => DashboardFileResource::collection($recentFiles->items()),
                'pagination' => [
                    'current_page' => $recentFiles->currentPage(),
                    'per_page' => $recentFiles->perPage(),
                    'total' => $recentFiles->total(),
                ],
            ],
            'recent_activity' => [
                'items' => DashboardActivityResource::collection($recentActivity->items()),
                'pagination' => [
                    'current_page' => $recentActivity->currentPage(),
                    'per_page' => $recentActivity->perPage(),
                    'total' => $recentActivity->total(),
                ],
            ],
        ];
    }
}
