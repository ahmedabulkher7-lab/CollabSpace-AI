<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Resources\FileAttachmentResource;
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
     * Get aggregated dashboard statistics and recent activity for the user.
     *
     * @return array<string, mixed>
     */
    public function getDashboardStats(User $user): array
    {
        $isAdmin = $user->hasRole('Administrator');

        // Projects Query
        $projectsQuery = Project::query();
        if (! $isAdmin) {
            $projectsQuery->where(function (Builder $query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('directMembers', fn (Builder $q) => $q->where('users.id', $user->id))
                    ->orWhereHas('teams.members', fn (Builder $q) => $q->where('users.id', $user->id));
            });
        }
        $accessibleProjectIds = (clone $projectsQuery)->pluck('id')->toArray();

        $totalProjects = count($accessibleProjectIds);
        $completedProjects = (clone $projectsQuery)->where('status', 'Completed')->count();
        $activeProjects = $totalProjects - $completedProjects;
        $averageProgress = $totalProjects > 0
            ? round((float) (clone $projectsQuery)->avg('progress_percentage'), 1)
            : 0.0;

        // Tasks Query
        $tasksQuery = Task::whereIn('project_id', $accessibleProjectIds);
        $totalTasks = (clone $tasksQuery)->count();
        $assignedToMe = (clone $tasksQuery)->where('assigned_to', $user->id)->count();
        $completedTasks = (clone $tasksQuery)->where('status', TaskStatus::Completed->value)->count();
        $overdueTasks = (clone $tasksQuery)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today())
            ->count();

        // Tasks by Status
        $tasksByStatus = [];
        foreach (TaskStatus::cases() as $status) {
            $tasksByStatus[$status->value] = (clone $tasksQuery)->where('status', $status->value)->count();
        }

        // Tasks by Priority
        $tasksByPriority = [];
        foreach (TaskPriority::cases() as $priority) {
            $tasksByPriority[$priority->value] = (clone $tasksQuery)->where('priority', $priority->value)->count();
        }

        // Meetings
        $upcomingMeetingsQuery = Meeting::whereIn('project_id', $accessibleProjectIds)
            ->where('meeting_date', '>=', Carbon::today())
            ->orderBy('meeting_date')
            ->orderBy('start_time');

        $upcomingMeetingsCount = (clone $upcomingMeetingsQuery)->count();
        $upcomingMeetings = (clone $upcomingMeetingsQuery)
            ->with(['project:id,name', 'creator:id,name,email,avatar_url'])
            ->limit(5)
            ->get();

        // Teams
        $teamsCount = $isAdmin
            ? Team::count()
            : Team::whereHas('members', fn (Builder $q) => $q->where('users.id', $user->id))->count();

        // Recent Activity
        $recentActivity = AuditLog::with('user:id,name,email,avatar_url')
            ->latest('created_at')
            ->limit(10)
            ->get();

        // Recent Files
        $recentFiles = FileAttachment::whereIn('project_id', $accessibleProjectIds)
            ->with(['uploader:id,name,email', 'project:id,name'])
            ->latest()
            ->limit(5)
            ->get();

        return [
            'projects' => [
                'total' => $totalProjects,
                'active' => $activeProjects,
                'completed' => $completedProjects,
                'average_progress' => $averageProgress,
            ],
            'tasks' => [
                'total' => $totalTasks,
                'assigned_to_me' => $assignedToMe,
                'completed' => $completedTasks,
                'overdue' => $overdueTasks,
                'by_status' => $tasksByStatus,
                'by_priority' => $tasksByPriority,
            ],
            'meetings' => [
                'upcoming_count' => $upcomingMeetingsCount,
                'upcoming' => $upcomingMeetings->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'meeting_date' => $m->meeting_date->toDateString(),
                    'start_time' => $m->start_time,
                    'end_time' => $m->end_time,
                    'meeting_link' => $m->meeting_link,
                    'project' => $m->project ? ['id' => $m->project->id, 'name' => $m->project->name] : null,
                    'creator' => $m->creator ? ['id' => $m->creator->id, 'name' => $m->creator->name] : null,
                ]),
            ],
            'teams' => [
                'total' => $teamsCount,
            ],
            'recent_activity' => $recentActivity->map(fn ($log) => [
                'id' => $log->id,
                'category' => $log->category->value ?? $log->category,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
            'recent_files' => FileAttachmentResource::collection($recentFiles),
        ];
    }
}
