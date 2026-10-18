<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReportService
{
    /**
     * Get Project Reports and metrics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getProjectReports(User $user, array $filters = []): array
    {
        $query = $this->getAccessibleProjectsQuery($user);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        $projects = $query->with(['teams', 'tasks'])->get();
        $totalProjects = $projects->count();

        $completedProjects = $projects->where('status', ProjectStatus::Completed->value)->count();
        $activeProjects = $projects->filter(fn (Project $p) => in_array($p->status, [ProjectStatus::Pending->value, ProjectStatus::InProgress->value], true))->count();
        $onHoldProjects = $projects->where('status', ProjectStatus::OnHold->value)->count();

        $delayedProjects = $projects->filter(function (Project $p) {
            return $p->status !== ProjectStatus::Completed->value
                && ! empty($p->deadline)
                && Carbon::parse($p->deadline)->isPast();
        })->count();

        $averageCompletionRate = $totalProjects > 0
            ? round((float) $projects->avg('progress_percentage'), 1)
            : 0.0;

        $projectList = $projects->map(function (Project $p) {
            $isDelayed = $p->status !== ProjectStatus::Completed->value && ! empty($p->deadline) && Carbon::parse($p->deadline)->isPast();
            $tasksCount = $p->tasks->count();
            $completedTasksCount = $p->tasks->where('status', TaskStatus::Completed)->count();

            return [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'priority' => $p->priority,
                'progress_percentage' => (float) $p->progress_percentage,
                'start_date' => $p->start_date?->toDateString(),
                'deadline' => $p->deadline?->toDateString(),
                'is_delayed' => $isDelayed,
                'teams_count' => $p->teams->count(),
                'tasks_count' => $tasksCount,
                'completed_tasks_count' => $completedTasksCount,
            ];
        });

        return [
            'summary' => [
                'total_projects' => $totalProjects,
                'active_projects' => $activeProjects,
                'delayed_projects' => $delayedProjects,
                'completed_projects' => $completedProjects,
                'on_hold_projects' => $onHoldProjects,
                'average_completion_rate' => $averageCompletionRate,
            ],
            'projects' => $projectList,
        ];
    }

    /**
     * Get Task Reports and productivity metrics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getTaskReports(User $user, array $filters = []): array
    {
        $projectIds = $this->getAccessibleProjectsQuery($user)->pluck('id')->toArray();
        $query = Task::whereIn('project_id', $projectIds)->with(['assignee', 'project', 'team']);

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        if (! empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }
        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        $tasks = $query->get();
        $totalTasks = $tasks->count();

        $completedTasks = $tasks->where('status', TaskStatus::Completed)->count();
        $inProgressTasks = $tasks->where('status', TaskStatus::InProgress)->count();
        $inReviewTasks = $tasks->where('status', TaskStatus::InReview)->count();
        $pendingTasks = $tasks->where('status', TaskStatus::Pending)->count();

        $overdueTasks = $tasks->filter(function (Task $t) {
            return $t->status !== TaskStatus::Completed
                && ! empty($t->due_date)
                && Carbon::parse($t->due_date)->isPast();
        })->count();

        // On-time completion: completed tasks where completed_at <= due_date
        $onTimeCompleted = $tasks->filter(function (Task $t) {
            if ($t->status !== TaskStatus::Completed || empty($t->completed_at) || empty($t->due_date)) {
                return false;
            }

            return Carbon::parse($t->completed_at)->lte(Carbon::parse($t->due_date)->endOfDay());
        })->count();

        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0.0;
        $onTimeRate = $completedTasks > 0 ? round(($onTimeCompleted / $completedTasks) * 100, 1) : 100.0;

        // Breakdown by Priority
        $byPriority = [];
        foreach (TaskPriority::cases() as $priority) {
            $byPriority[$priority->value] = $tasks->where('priority', $priority)->count();
        }

        // Breakdown by Status
        $byStatus = [];
        foreach (TaskStatus::cases() as $status) {
            $byStatus[$status->value] = $tasks->where('status', $status)->count();
        }

        return [
            'summary' => [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'in_progress_tasks' => $inProgressTasks,
                'in_review_tasks' => $inReviewTasks,
                'pending_tasks' => $pendingTasks,
                'overdue_tasks' => $overdueTasks,
                'completion_rate' => $completionRate,
                'on_time_completion_rate' => $onTimeRate,
            ],
            'by_priority' => $byPriority,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Get Team Performance Reports and workload distribution.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getTeamReports(User $user, array $filters = []): array
    {
        $isAdmin = $user->hasRole('Administrator');
        $query = Team::query();

        if (! $isAdmin) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('members', fn (Builder $sub) => $sub->where('users.id', $user->id));
            });
        }

        if (! empty($filters['team_id'])) {
            $query->where('id', $filters['team_id']);
        }

        $teams = $query->with(['members', 'projects.tasks', 'tasks'])->get();
        $totalWorkspaceTasks = Task::count();

        $teamData = $teams->map(function (Team $team) use ($totalWorkspaceTasks) {
            $membersCount = $team->members->count();
            $activeProjectsCount = $team->projects->whereIn('status', [ProjectStatus::Pending->value, ProjectStatus::InProgress->value])->count();

            $tasks = $team->tasks;
            $teamTasksCount = $tasks->count();
            $completedTasks = $tasks->where('status', TaskStatus::Completed)->count();
            $pendingTasks = $tasks->where('status', '!=', TaskStatus::Completed)->count();

            $completionRate = $teamTasksCount > 0 ? round(($completedTasks / $teamTasksCount) * 100, 1) : 0.0;
            $workloadShare = $totalWorkspaceTasks > 0 ? round(($teamTasksCount / $totalWorkspaceTasks) * 100, 1) : 0.0;

            return [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'description' => $team->description,
                'members_count' => $membersCount,
                'active_projects_count' => $activeProjectsCount,
                'total_tasks' => $teamTasksCount,
                'completed_tasks' => $completedTasks,
                'pending_tasks' => $pendingTasks,
                'completion_rate' => $completionRate,
                'workload_share_percentage' => $workloadShare,
            ];
        });

        return [
            'summary' => [
                'total_teams' => $teams->count(),
                'total_members' => $teams->sum(fn ($t) => $t->members->count()),
                'total_assigned_tasks' => $teams->sum(fn ($t) => $t->tasks->count()),
            ],
            'teams' => $teamData,
        ];
    }

    /**
     * Get Individual User Productivity Report.
     */
    public function getUserReports(User $currentUser, ?int $targetUserId = null): array
    {
        $targetUser = $currentUser;

        if ($targetUserId && $targetUserId !== $currentUser->id) {
            if (! $currentUser->hasRole('Administrator') && ! $currentUser->hasRole('Project Manager')) {
                throw new AccessDeniedHttpException('Only Administrators and Project Managers can view reports of other users.');
            }
            $targetUser = User::findOrFail($targetUserId);
        }

        $tasks = $targetUser->assignedTasks()->get();
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', TaskStatus::Completed)->count();
        $inProgressTasks = $tasks->where('status', TaskStatus::InProgress)->count();
        $pendingTasks = $tasks->where('status', TaskStatus::Pending)->count();

        $overdueTasks = $tasks->filter(function (Task $t) {
            return $t->status !== TaskStatus::Completed && ! empty($t->due_date) && Carbon::parse($t->due_date)->isPast();
        })->count();

        // On-time completion
        $onTimeTasks = $tasks->filter(function (Task $t) {
            return $t->status === TaskStatus::Completed
                && ! empty($t->completed_at)
                && ! empty($t->due_date)
                && Carbon::parse($t->completed_at)->lte(Carbon::parse($t->due_date)->endOfDay());
        })->count();

        $taskCompletionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0.0;
        $onTimeRate = $completedTasks > 0 ? round(($onTimeTasks / $completedTasks) * 100, 1) : 100.0;

        // Meetings Attendance
        $meetings = $targetUser->meetings()->get();
        $totalMeetings = $meetings->count();
        $attendedMeetings = $meetings->where('pivot.attended', true)->count();
        $attendanceRate = $totalMeetings > 0 ? round(($attendedMeetings / $totalMeetings) * 100, 1) : 100.0;

        // Uploaded files
        $uploadedFilesCount = $targetUser->uploadedFiles()->count();
        $totalFileSize = (int) $targetUser->uploadedFiles()->sum('file_size');

        // Productivity Score calculation (weighted formula: 50% task completion + 25% on-time + 25% attendance)
        $score = round(($taskCompletionRate * 0.5) + ($onTimeRate * 0.25) + ($attendanceRate * 0.25), 1);
        $score = (float) min(100.0, max(0.0, $score));

        $performanceLevel = match (true) {
            $score >= 85 => 'Outstanding',
            $score >= 70 => 'High Performer',
            $score >= 50 => 'Good',
            default => 'Needs Improvement',
        };

        return [
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'job_title' => $targetUser->job_title,
                'years_of_experience' => $targetUser->years_of_experience,
                'roles' => $targetUser->getRoleNames(),
            ],
            'tasks' => [
                'total_assigned' => $totalTasks,
                'completed' => $completedTasks,
                'in_progress' => $inProgressTasks,
                'pending' => $pendingTasks,
                'overdue' => $overdueTasks,
                'completion_rate' => $taskCompletionRate,
                'on_time_rate' => $onTimeRate,
            ],
            'meetings' => [
                'total_invited' => $totalMeetings,
                'attended' => $attendedMeetings,
                'attendance_rate' => $attendanceRate,
            ],
            'files' => [
                'uploaded_count' => $uploadedFilesCount,
                'total_size_bytes' => $totalFileSize,
                'formatted_size' => $this->formatFileSize($totalFileSize),
            ],
            'productivity' => [
                'score' => $score,
                'performance_level' => $performanceLevel,
            ],
        ];
    }

    /**
     * Accessible projects query helper.
     */
    protected function getAccessibleProjectsQuery(User $user): Builder
    {
        $isAdmin = $user->hasRole('Administrator');
        $query = Project::query();

        if (! $isAdmin) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('directMembers', fn (Builder $sub) => $sub->where('users.id', $user->id))
                    ->orWhereHas('teams.members', fn (Builder $sub) => $sub->where('users.id', $user->id));
            });
        }

        return $query;
    }

    /**
     * Format file size.
     */
    protected function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
