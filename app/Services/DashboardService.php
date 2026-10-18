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
     * Get aggregated dashboard statistics matching Figma design schema.
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

        // Projects Overview Metrics
        $totalProjects = count($accessibleProjectIds);
        $completedProjects = (clone $projectsQuery)->where('status', ProjectStatus::Completed->value)->count();
        $activeProjectsCount = $totalProjects - $completedProjects;
        $averageProgress = $totalProjects > 0
            ? round((float) (clone $projectsQuery)->avg('progress_percentage'), 1)
            : 0.0;

        // 2. Tasks Query
        $tasksQuery = Task::whereIn('project_id', $accessibleProjectIds);
        $totalTasks = (clone $tasksQuery)->count();
        $assignedToMe = (clone $tasksQuery)->where('assigned_to', $user->id)->count();
        $completedTasksCount = (clone $tasksQuery)->where('status', TaskStatus::Completed->value)->count();
        $pendingTasksCount = (clone $tasksQuery)->where('status', TaskStatus::Pending->value)->count();
        $inProgressTasksCount = (clone $tasksQuery)->where('status', TaskStatus::InProgress->value)->count();
        $inReviewTasksCount = (clone $tasksQuery)->where('status', TaskStatus::InReview->value)->count();

        $overdueTasksCount = (clone $tasksQuery)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today())
            ->count();

        $completionRate = $totalTasks > 0
            ? round(($completedTasksCount / $totalTasks) * 100, 1)
            : 0;

        // Tasks by Priority (lowercase keys)
        $byPriority = [
            'low' => (clone $tasksQuery)->where('priority', TaskPriority::Low->value)->count(),
            'medium' => (clone $tasksQuery)->where('priority', TaskPriority::Medium->value)->count(),
            'high' => (clone $tasksQuery)->where('priority', TaskPriority::High->value)->count(),
            'critical' => (clone $tasksQuery)->where('priority', TaskPriority::Critical->value)->count(),
        ];

        // 3. Meetings
        $upcomingMeetingsQuery = Meeting::whereIn('project_id', $accessibleProjectIds)
            ->where('meeting_date', '>=', Carbon::today())
            ->orderBy('meeting_date')
            ->orderBy('start_time');

        $upcomingMeetingsCount = (clone $upcomingMeetingsQuery)->count();

        // 4. Teams
        $teamsQuery = $isAdmin
            ? Team::query()
            : Team::whereHas('members', fn (Builder $q) => $q->where('users.id', $user->id));
        $teamsTotal = (clone $teamsQuery)->count();

        // 5. Project Overview Graph (Period: Year, Group By: Month)
        $currentYear = Carbon::now()->year;
        $projectOverviewData = [];
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

        // 6. Active Projects List (Paginated)
        $activeProjectsPaginated = (clone $projectsQuery)
            ->where('status', '!=', ProjectStatus::Completed->value)
            ->with(['tasks'])
            ->latest('created_at')
            ->paginate(5);

        $activeProjectsItems = $activeProjectsPaginated->map(function (Project $p) {
            $tasksTotal = $p->tasks->count();
            $tasksCompleted = $p->tasks->where('status', TaskStatus::Completed)->count();

            return [
                'id' => $p->id,
                'name' => $p->name,
                'progress' => (float) $p->progress_percentage,
                'tasks_total' => $tasksTotal > 0 ? $tasksTotal : null,
                'tasks_completed' => $tasksTotal > 0 ? $tasksCompleted : null,
            ];
        });

        // 7. Tasks List (Paginated)
        $tasksPaginated = (clone $tasksQuery)
            ->with(['project:id,name', 'assignee:id,name,avatar_url'])
            ->latest('created_at')
            ->paginate(5);

        $tasksItems = $tasksPaginated->map(fn (Task $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'priority' => strtolower($t->priority->value ?? $t->priority),
            'status' => strtolower(str_replace(' ', '_', $t->status->value ?? $t->status)),
            'status_label' => $t->status->value ?? $t->status,
            'due_date' => $t->due_date ? Carbon::parse($t->due_date)->toDateString() : null,
            'project' => $t->project ? ['id' => $t->project->id, 'name' => $t->project->name] : null,
            'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name, 'avatar_url' => $t->assignee->avatar_url] : null,
        ]);

        // 8. Teams List (Paginated)
        $teamsPaginated = (clone $teamsQuery)
            ->withCount(['members', 'projects'])
            ->latest('created_at')
            ->paginate(10);

        $teamsItems = $teamsPaginated->map(fn (Team $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'description' => $t->description,
            'members_count' => (int) $t->members_count,
            'projects_count' => (int) $t->projects_count,
        ]);

        // 9. Upcoming Meetings List
        $upcomingMeetings = (clone $upcomingMeetingsQuery)
            ->with([
                'project:id,name',
                'creator:id,name',
                'participants:id,name,avatar_url',
            ])
            ->limit(5)
            ->get();

        $meetingsItems = $upcomingMeetings->map(fn (Meeting $m) => [
            'id' => $m->id,
            'title' => $m->title,
            'date' => $m->meeting_date->toDateString(),
            'start_time' => $m->start_time,
            'end_time' => $m->end_time,
            'meeting_link' => $m->meeting_link,
            'project' => $m->project ? [
                'id' => $m->project->id,
                'name' => $m->project->name,
            ] : null,
            'creator' => $m->creator ? [
                'id' => $m->creator->id,
                'name' => $m->creator->name,
            ] : null,
            'participants' => $m->participants->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => $u->avatar_url,
            ])->values(),
        ]);

        // 10. Recent Files (Paginated)
        $recentFilesPaginated = FileAttachment::whereIn('project_id', $accessibleProjectIds)
            ->with(['uploader:id,name', 'project:id,name'])
            ->latest('created_at')
            ->paginate(5);

        $recentFilesItems = $recentFilesPaginated->map(fn (FileAttachment $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'mime_type' => $f->mime_type,
            'file_size_formatted' => $this->formatFileSize($f->file_size),
            'download_url' => "/api/v1/files/{$f->id}/download",
            'uploaded_by' => $f->uploader ? [
                'id' => $f->uploader->id,
                'name' => $f->uploader->name,
            ] : null,
            'project' => $f->project ? [
                'id' => $f->project->id,
                'name' => $f->project->name,
            ] : null,
            'created_at' => $f->created_at?->toIso8601String(),
        ]);

        // 11. Recent Activity (Paginated)
        $recentActivityPaginated = AuditLog::with('user:id,name')
            ->latest('created_at')
            ->paginate(10);

        $recentActivityItems = $recentActivityPaginated->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'category' => $log->category->value ?? $log->category,
            'action' => $log->action,
            'description' => $log->description,
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
            ] : null,
            'created_at' => $log->created_at?->toIso8601String(),
        ]);

        // Complete Structured Response Matching UI Design
        return [
            'overview' => [
                'projects' => [
                    'total' => $totalProjects,
                    'active' => $activeProjectsCount,
                    'completed' => $completedProjects,
                    'average_progress' => $averageProgress,
                ],
                'tasks' => [
                    'total' => $totalTasks,
                    'pending' => $pendingTasksCount,
                    'in_progress' => $inProgressTasksCount,
                    'in_review' => $inReviewTasksCount,
                    'completed' => $completedTasksCount,
                    'overdue' => $overdueTasksCount,
                    'assigned_to_me' => $assignedToMe,
                    'completion_rate' => $completionRate,
                    'by_priority' => $byPriority,
                ],
                'meetings' => [
                    'upcoming_count' => $upcomingMeetingsCount,
                ],
                'teams' => [
                    'total' => $teamsTotal,
                ],
            ],
            'project_overview' => [
                'period' => 'year',
                'group_by' => 'month',
                'data' => $projectOverviewData,
            ],
            'active_projects' => [
                'items' => $activeProjectsItems->values(),
                'pagination' => [
                    'current_page' => $activeProjectsPaginated->currentPage(),
                    'per_page' => $activeProjectsPaginated->perPage(),
                    'total' => $activeProjectsPaginated->total(),
                ],
            ],
            'tasks' => [
                'items' => $tasksItems->values(),
                'pagination' => [
                    'current_page' => $tasksPaginated->currentPage(),
                    'per_page' => $tasksPaginated->perPage(),
                    'total' => $tasksPaginated->total(),
                ],
            ],
            'teams' => [
                'items' => $teamsItems->values(),
                'pagination' => [
                    'current_page' => $teamsPaginated->currentPage(),
                    'per_page' => $teamsPaginated->perPage(),
                    'total' => $teamsPaginated->total(),
                ],
            ],
            'upcoming_meetings' => [
                'items' => $meetingsItems->values(),
            ],
            'recent_files' => [
                'items' => $recentFilesItems->values(),
                'pagination' => [
                    'current_page' => $recentFilesPaginated->currentPage(),
                    'per_page' => $recentFilesPaginated->perPage(),
                    'total' => $recentFilesPaginated->total(),
                ],
            ],
            'recent_activity' => [
                'items' => $recentActivityItems->values(),
                'pagination' => [
                    'current_page' => $recentActivityPaginated->currentPage(),
                    'per_page' => $recentActivityPaginated->perPage(),
                    'total' => $recentActivityPaginated->total(),
                ],
            ],
        ];
    }

    /**
     * Format file size in human-readable units.
     */
    protected function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
