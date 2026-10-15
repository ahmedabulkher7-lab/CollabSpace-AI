<?php

namespace App\Services;

use App\Enums\AuditLogCategory;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    /**
     * Get a paginated list of projects with filters and counts.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Project>
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(['teams', 'creator'])
            ->withCount(['files', 'tasks']);

        if (! empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        if (! empty($filters['status']) && in_array($filters['status'], ProjectStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority']) && in_array($filters['priority'], ProjectPriority::values(), true)) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['sort']) && strtolower($filters['sort']) === 'oldest') {
            $query->oldest();
        } else {
            $query->latest();
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new project and assign teams and members.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $creator): Project
    {
        return DB::transaction(function () use ($data, $creator) {
            $project = Project::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'],
                'deadline' => $data['deadline'],
                'priority' => $data['priority'] ?? ProjectPriority::Medium->value,
                'status' => $data['status'] ?? ProjectStatus::Pending->value,
                'progress_percentage' => 0.00,
                'created_by' => $creator->id,
            ]);

            // Add creator to directMembers as Owner/Lead
            $project->directMembers()->attach($creator->id, ['role' => 'Owner']);

            // Attach teams if provided
            if (! empty($data['teams']) && is_array($data['teams'])) {
                $project->teams()->sync($data['teams']);
            }

            // Attach direct members if provided
            if (! empty($data['members']) && is_array($data['members'])) {
                foreach ($data['members'] as $member) {
                    if ($member['user_id'] != $creator->id) {
                        $project->directMembers()->syncWithoutDetaching([
                            $member['user_id'] => ['role' => $member['role'] ?? 'Contributor'],
                        ]);
                    }
                }
            }

            AuditLog::create([
                'user_id' => $creator->id,
                'category' => AuditLogCategory::Project->value,
                'action' => 'project_created',
                'description' => "Created project: {$project->name}",
                'payload' => ['project_id' => $project->id, 'name' => $project->name],
            ]);

            return $project->load(['teams', 'directMembers', 'creator'])->loadCount('files');
        });
    }

    /**
     * Update an existing project.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data, User $user): Project
    {
        return DB::transaction(function () use ($project, $data, $user) {
            $project->update($data);

            if (isset($data['teams']) && is_array($data['teams'])) {
                $project->teams()->sync($data['teams']);
            }

            $project->updateProgress();

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Project->value,
                'action' => 'project_updated',
                'description' => "Updated project: {$project->name}",
                'payload' => ['project_id' => $project->id, 'changes' => array_keys($data)],
            ]);

            return $project->load(['teams', 'directMembers', 'creator'])->loadCount('files');
        });
    }

    /**
     * Delete a project.
     */
    public function delete(Project $project, User $user): void
    {
        $projectName = $project->name;
        $projectId = $project->id;

        $project->delete();

        AuditLog::create([
            'user_id' => $user->id,
            'category' => AuditLogCategory::Project->value,
            'action' => 'project_deleted',
            'description' => "Deleted project: {$projectName}",
            'payload' => ['project_id' => $projectId, 'name' => $projectName],
        ]);
    }

    /**
     * Retrieve project teams with task progress and member ratios for Figma All Projects / Alpha / teams.
     *
     * @return Collection<int, object>
     */
    public function getProjectTeamsWithProgress(Project $project): Collection
    {
        $teams = $project->teams()->with('members')->get();

        return $teams->map(function ($team) use ($project) {
            // Team total tasks in this project
            $teamTotalTasks = $project->tasks()->where('team_id', $team->id)->count();
            $teamCompletedTasks = $project->tasks()
                ->where('team_id', $team->id)
                ->where('status', TaskStatus::Completed->value)
                ->count();

            $teamCompletionPercentage = $teamTotalTasks > 0
                ? round(($teamCompletedTasks / $teamTotalTasks) * 100, 1)
                : 0.0;

            // Members progress inside this project
            $membersProgress = $team->members->map(function ($member) use ($project) {
                $userTotalTasks = $project->tasks()->where('assigned_to', $member->id)->count();
                $userCompletedTasks = $project->tasks()
                    ->where('assigned_to', $member->id)
                    ->where('status', TaskStatus::Completed->value)
                    ->count();

                $userProgress = $userTotalTasks > 0
                    ? round(($userCompletedTasks / $userTotalTasks) * 100, 1)
                    : 0.0;

                $roleInTeam = $member->pivot?->role_in_team ?? 'Member';

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'avatar_url' => $member->avatar_url,
                    'role_in_team' => $roleInTeam,
                    'is_leader' => strcasecmp($roleInTeam, 'Leader') === 0 || strcasecmp($roleInTeam, 'Lead') === 0 || strcasecmp($roleInTeam, 'Lead Engineer') === 0,
                    'tasks_completed' => $userCompletedTasks,
                    'tasks_total' => $userTotalTasks,
                    'tasks_ratio' => sprintf('%02d/%02d', $userCompletedTasks, $userTotalTasks),
                    'progress_percentage' => $userProgress,
                ];
            });

            $team->completion_percentage = $teamCompletionPercentage;
            $team->total_tasks = $teamTotalTasks;
            $team->completed_tasks = $teamCompletedTasks;
            $team->members_progress = $membersProgress;

            return $team;
        });
    }

    /**
     * Assign teams to a project.
     *
     * @param  array<int, int>  $teamIds
     */
    public function assignTeams(Project $project, array $teamIds, User $user): Project
    {
        $project->teams()->syncWithoutDetaching($teamIds);

        AuditLog::create([
            'user_id' => $user->id,
            'category' => AuditLogCategory::Project->value,
            'action' => 'project_teams_assigned',
            'description' => "Assigned teams to project: {$project->name}",
            'payload' => ['project_id' => $project->id, 'team_ids' => $teamIds],
        ]);

        return $project->load(['teams', 'creator']);
    }

    /**
     * Remove a team from a project.
     */
    public function removeTeam(Project $project, int $teamId, User $user): Project
    {
        $project->teams()->detach($teamId);

        AuditLog::create([
            'user_id' => $user->id,
            'category' => AuditLogCategory::Project->value,
            'action' => 'project_team_removed',
            'description' => "Removed team ID {$teamId} from project: {$project->name}",
            'payload' => ['project_id' => $project->id, 'team_id' => $teamId],
        ]);

        return $project->load(['teams', 'creator']);
    }
}
