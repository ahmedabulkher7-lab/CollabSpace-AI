<?php

namespace App\Services;

use App\Enums\AuditLogCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TaskService
{
    /**
     * Get paginated tasks with comprehensive filters.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Task>
     */
    public function getAll(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->getBaseQuery($user);

        $this->applyFilters($query, $filters);

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $direction = strtolower($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['name', 'due_date', 'created_at', 'priority', 'status'], true)) {
            $query->orderBy($sortBy, $direction);
        } else {
            $query->latest();
        }

        return $query->paginate($perPage);
    }

    /**
     * Get Kanban board columns for a given project.
     *
     * @return array<string, mixed>
     */
    public function getKanbanBoard(User $user, int $projectId): array
    {
        $project = Project::findOrFail($projectId);

        $tasks = $this->getBaseQuery($user)
            ->where('project_id', $projectId)
            ->orderBy('due_date', 'asc')
            ->get();

        $columns = [];
        foreach (TaskStatus::cases() as $status) {
            $columnTasks = $tasks->where('status', $status)->values();
            $columns[$status->value] = [
                'name' => $status->value,
                'count' => $columnTasks->count(),
                'tasks' => $columnTasks,
            ];
        }

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'progress_percentage' => $project->progress_percentage,
            ],
            'total_tasks' => $tasks->count(),
            'columns' => $columns,
        ];
    }

    /**
     * Create a new task.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Task
    {
        return DB::transaction(function () use ($user, $data) {
            $data['created_by'] = $user->id;

            if (isset($data['status']) && $data['status'] === TaskStatus::Completed->value) {
                $data['completed_at'] = Carbon::now();
            }

            /** @var Task $task */
            $task = Task::create($data);

            // Recalculate project progress
            $task->project->updateProgress();

            // Notify assignee if assigned to someone else
            if (! empty($task->assigned_to) && $task->assigned_to !== $user->id) {
                $task->assignee?->notify(new TaskAssignedNotification($task, $task->project, $user));
            }

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Task->value,
                'action' => 'created',
                'description' => "Task '{$task->name}' created in project '{$task->project->name}'.",
                'payload' => [
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                    'assigned_to' => $task->assigned_to,
                    'priority' => $task->priority->value ?? $task->priority,
                    'status' => $task->status->value ?? $task->status,
                ],
            ]);

            return $task->load(['project', 'team', 'assignee', 'creator'])->loadCount('attachments');
        });
    }

    /**
     * Update an existing task.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Task $task, array $data): Task
    {
        return DB::transaction(function () use ($user, $task, $data) {
            if (isset($data['status'])) {
                $currentStatus = $task->status instanceof TaskStatus ? $task->status->value : $task->status;

                if ($data['status'] === TaskStatus::Completed->value && $currentStatus !== TaskStatus::Completed->value) {
                    $data['completed_at'] = Carbon::now();
                } elseif ($data['status'] !== TaskStatus::Completed->value && $currentStatus === TaskStatus::Completed->value) {
                    $data['completed_at'] = null;
                }
            }

            $oldProjectId = $task->project_id;
            $oldAssignedTo = $task->assigned_to;
            $task->update($data);

            // Notify new assignee if changed
            if (isset($data['assigned_to']) && $task->assigned_to != $oldAssignedTo && ! empty($task->assigned_to) && $task->assigned_to !== $user->id) {
                $task->assignee?->notify(new TaskAssignedNotification($task, $task->project, $user));
            }

            // Recalculate progress for project(s)
            $task->project->updateProgress();
            if (isset($data['project_id']) && $data['project_id'] !== $oldProjectId) {
                Project::find($oldProjectId)?->updateProgress();
            }

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Task->value,
                'action' => 'updated',
                'description' => "Task '{$task->name}' updated.",
                'payload' => array_keys($data),
            ]);

            return $task->fresh(['project', 'team', 'assignee', 'creator'])->loadCount('attachments');
        });
    }

    /**
     * Update task status (specifically for Kanban board drag & drop).
     */
    public function updateStatus(User $user, Task $task, string $status): Task
    {
        return DB::transaction(function () use ($user, $task, $status) {
            $data = ['status' => $status];

            if ($status === TaskStatus::Completed->value) {
                $data['completed_at'] = Carbon::now();
            } else {
                $data['completed_at'] = null;
            }

            $task->update($data);
            $task->project->updateProgress();

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Task->value,
                'action' => 'status_changed',
                'description' => "Task '{$task->name}' status changed to '{$status}'.",
                'payload' => [
                    'task_id' => $task->id,
                    'status' => $status,
                ],
            ]);

            return $task->fresh(['project', 'team', 'assignee', 'creator'])->loadCount('attachments');
        });
    }

    /**
     * Assign or reassign task to user and/or team.
     */
    public function assign(User $user, Task $task, ?int $assignedTo, ?int $teamId): Task
    {
        return DB::transaction(function () use ($user, $task, $assignedTo, $teamId) {
            $task->update([
                'assigned_to' => $assignedTo,
                'team_id' => $teamId,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Task->value,
                'action' => 'assigned',
                'description' => "Task '{$task->name}' assigned to user ID {$assignedTo} and team ID {$teamId}.",
                'payload' => [
                    'task_id' => $task->id,
                    'assigned_to' => $assignedTo,
                    'team_id' => $teamId,
                ],
            ]);

            return $task->fresh(['project', 'team', 'assignee', 'creator'])->loadCount('attachments');
        });
    }

    /**
     * Delete a task.
     */
    public function delete(User $user, Task $task): void
    {
        DB::transaction(function () use ($user, $task) {
            $project = $task->project;
            $taskName = $task->name;
            $taskId = $task->id;

            $task->delete();
            $project->updateProgress();

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Task->value,
                'action' => 'deleted',
                'description' => "Task '{$taskName}' deleted from project '{$project->name}'.",
                'payload' => ['task_id' => $taskId, 'project_id' => $project->id],
            ]);
        });
    }

    /**
     * Build base query with eager loading and user access scopes.
     *
     * @return Builder<Task>
     */
    protected function getBaseQuery(User $user): Builder
    {
        $query = Task::query()
            ->with(['project', 'team', 'assignee', 'creator'])
            ->withCount('attachments');

        // Scoping: Admin sees all; PM and Members see tasks in projects they have access to
        if (! $user->hasRole('Administrator') && ! $user->can('projects.view-all')) {
            $query->whereHas('project', function (Builder $projectQuery) use ($user) {
                $projectQuery->where('created_by', $user->id)
                    ->orWhereHas('directMembers', fn ($q) => $q->where('users.id', $user->id))
                    ->orWhereHas('teams.members', fn ($q) => $q->where('users.id', $user->id));
            });
        }

        return $query;
    }

    /**
     * Apply filter parameters to task query.
     *
     * @param  Builder<Task>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (! empty($filters['status']) && in_array($filters['status'], TaskStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority']) && in_array($filters['priority'], TaskPriority::values(), true)) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['due_date_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_date_from']);
        }

        if (! empty($filters['due_date_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_date_to']);
        }
    }
}
