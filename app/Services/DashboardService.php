<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\FileAttachment;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    /**
     * Aggregate dashboard metrics, paginators, and raw data models.
     * Presentation formatting is handled exclusively by API Resources.
     *
     * @return array<string, mixed>
     */
    public function getDashboardStats(User $user): array
    {
        $isAdmin = $user->hasRole('Administrator');

        // 1. Accessible Projects Query
        $projectsQuery = Project::query();
        if (! $isAdmin) {
            $projectsQuery->where(function (Builder $query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('directMembers', fn (Builder $q) => $q->where('users.id', $user->id))
                    ->orWhereHas('teams.members', fn (Builder $q) => $q->where('users.id', $user->id));
            });
        }
        $accessibleProjectIds = (clone $projectsQuery)->pluck('id')->toArray();

        // 2. Overview Metrics Calculation
        $overview = $this->calculateOverviewMetrics($projectsQuery, $accessibleProjectIds, $user, $isAdmin);

        // 3. Project Overview Monthly Graph
        $projectOverview = $this->calculateProjectOverviewGraph($projectsQuery, $accessibleProjectIds);

        // 4. Paginated Collections & Data Models
        $activeProjects = (clone $projectsQuery)
            ->where('status', '!=', ProjectStatus::Completed->value)
            ->with(['tasks'])
            ->latest('created_at')
            ->paginate(5);

        $tasks = Task::whereIn('project_id', $accessibleProjectIds)
            ->with(['project:id,name', 'assignee:id,name,avatar_url'])
            ->latest('created_at')
            ->paginate(5);

        $teams = ($isAdmin
            ? Team::query()
            : Team::whereHas('members', fn (Builder $q) => $q->where('users.id', $user->id)))
            ->withCount(['members', 'projects'])
            ->latest('created_at')
            ->paginate(10);

        $upcomingMeetings = Meeting::whereIn('project_id', $accessibleProjectIds)
            ->where('meeting_date', '>=', Carbon::today())
            ->with([
                'project:id,name',
                'creator:id,name',
                'participants:id,name,avatar_url',
            ])
            ->orderBy('meeting_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get();

        $recentFiles = FileAttachment::whereIn('project_id', $accessibleProjectIds)
            ->with(['uploader:id,name', 'project:id,name'])
            ->latest('created_at')
            ->paginate(5);

        $recentActivity = AuditLog::with('user:id,name')
            ->latest('created_at')
            ->paginate(10);

        return [
            'overview' => $overview,
            'project_overview' => $projectOverview,
            'active_projects' => $activeProjects,
            'tasks' => $tasks,
            'teams' => $teams,
            'upcoming_meetings' => $upcomingMeetings,
            'recent_files' => $recentFiles,
            'recent_activity' => $recentActivity,
        ];
    }

    /**
     * Calculate aggregated metrics for projects, tasks, meetings, and teams.
     *
     * @param  array<int>  $accessibleProjectIds
     * @return array<string, mixed>
     */
    protected function calculateOverviewMetrics(Builder $projectsQuery, array $accessibleProjectIds, User $user, bool $isAdmin): array
    {
        $totalProjects = count($accessibleProjectIds);
        $completedProjects = (clone $projectsQuery)->where('status', ProjectStatus::Completed->value)->count();
        $activeProjectsCount = $totalProjects - $completedProjects;
        $averageProgress = $totalProjects > 0
            ? round((float) (clone $projectsQuery)->avg('progress_percentage'), 1)
            : 0.0;

        $tasksQuery = Task::whereIn('project_id', $accessibleProjectIds);
        $totalTasks = (clone $tasksQuery)->count();
        $completedTasksCount = (clone $tasksQuery)->where('status', TaskStatus::Completed->value)->count();

        $byPriority = [
            'low' => (clone $tasksQuery)->where('priority', TaskPriority::Low->value)->count(),
            'medium' => (clone $tasksQuery)->where('priority', TaskPriority::Medium->value)->count(),
            'high' => (clone $tasksQuery)->where('priority', TaskPriority::High->value)->count(),
            'critical' => (clone $tasksQuery)->where('priority', TaskPriority::Critical->value)->count(),
        ];

        $overdueTasksCount = (clone $tasksQuery)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today())
            ->count();

        $completionRate = $totalTasks > 0
            ? round(($completedTasksCount / $totalTasks) * 100, 1)
            : 0;

        $upcomingMeetingsCount = Meeting::whereIn('project_id', $accessibleProjectIds)
            ->where('meeting_date', '>=', Carbon::today())
            ->count();

        $teamsTotal = ($isAdmin
            ? Team::query()
            : Team::whereHas('members', fn (Builder $q) => $q->where('users.id', $user->id)))
            ->count();

        return [
            'projects' => [
                'total' => $totalProjects,
                'active' => $activeProjectsCount,
                'completed' => $completedProjects,
                'average_progress' => $averageProgress,
            ],
            'tasks' => [
                'total' => $totalTasks,
                'pending' => (clone $tasksQuery)->where('status', TaskStatus::Pending->value)->count(),
                'in_progress' => (clone $tasksQuery)->where('status', TaskStatus::InProgress->value)->count(),
                'in_review' => (clone $tasksQuery)->where('status', TaskStatus::InReview->value)->count(),
                'completed' => $completedTasksCount,
                'overdue' => $overdueTasksCount,
                'assigned_to_me' => (clone $tasksQuery)->where('assigned_to', $user->id)->count(),
                'completion_rate' => $completionRate,
                'by_priority' => $byPriority,
            ],
            'meetings' => [
                'upcoming_count' => $upcomingMeetingsCount,
            ],
            'teams' => [
                'total' => $teamsTotal,
            ],
        ];
    }

    /**
     * Calculate monthly project and task timeline metrics for the current year.
     *
     * @param  array<int>  $accessibleProjectIds
     * @return array<string, mixed>
     */
    protected function calculateProjectOverviewGraph(Builder $projectsQuery, array $accessibleProjectIds): array
    {
        $currentYear = Carbon::now()->year;
        $projectOverviewData = [];
        $tasksQuery = Task::whereIn('project_id', $accessibleProjectIds);

        for ($m = 1; $m <= 12; $m++) {
            $monthDate = Carbon::create($currentYear, $m, 1);
            $monthProjects = (clone $projectsQuery)
                ->whereMonth('created_at', $m)
                ->whereYear('created_at', $currentYear)
                ->count();
            $monthCompletedTasks = (clone $tasksQuery)
                ->where('status', TaskStatus::Completed->value)
                ->whereMonth('completed_at', $m)
                ->whereYear('completed_at', $currentYear)
                ->count();

            $projectOverviewData[] = [
                'month' => $monthDate->format('M'),
                'month_number' => $m,
                'projects' => $monthProjects,
                'completed_tasks' => $monthCompletedTasks,
            ];
        }

        return [
            'period' => 'year',
            'group_by' => 'month',
            'data' => $projectOverviewData,
        ];
    }
}
