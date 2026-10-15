<?php

namespace App\Services;

use App\Enums\AuditLogCategory;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TeamService
{
    /**
     * Get a paginated list of teams with eager loaded counts and relations.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Team>
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Team::query()
            ->with(['creator', 'members'])
            ->withCount(['members', 'projects']);

        if (! empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Create a new team and optionally attach initial members.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $creator): Team
    {
        return DB::transaction(function () use ($data, $creator) {
            $team = Team::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $creator->id,
            ]);

            // Always add creator to the team as Leader if not in members
            $hasCreator = false;

            if (! empty($data['members']) && is_array($data['members'])) {
                foreach ($data['members'] as $member) {
                    if ($member['user_id'] == $creator->id) {
                        $hasCreator = true;
                    }
                    $team->members()->attach($member['user_id'], [
                        'role_in_team' => $member['role_in_team'] ?? 'Member',
                    ]);
                }
            }

            if (! $hasCreator) {
                $team->members()->attach($creator->id, [
                    'role_in_team' => 'Leader',
                ]);
            }

            AuditLog::create([
                'user_id' => $creator->id,
                'category' => AuditLogCategory::Project->value,
                'action' => 'team_created',
                'description' => "Created team: {$team->name}",
                'payload' => ['team_id' => $team->id, 'name' => $team->name],
            ]);

            return $team->load(['creator', 'members'])->loadCount(['members', 'projects']);
        });
    }

    /**
     * Update an existing team.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Team $team, array $data, User $user): Team
    {
        $team->update($data);

        AuditLog::create([
            'user_id' => $user->id,
            'category' => AuditLogCategory::Project->value,
            'action' => 'team_updated',
            'description' => "Updated team: {$team->name}",
            'payload' => ['team_id' => $team->id, 'changes' => array_keys($data)],
        ]);

        return $team->load(['creator', 'members'])->loadCount(['members', 'projects']);
    }

    /**
     * Delete a team.
     */
    public function delete(Team $team, User $user): void
    {
        $teamName = $team->name;
        $teamId = $team->id;

        $team->delete();

        AuditLog::create([
            'user_id' => $user->id,
            'category' => AuditLogCategory::Project->value,
            'action' => 'team_deleted',
            'description' => "Deleted team: {$teamName}",
            'payload' => ['team_id' => $teamId, 'name' => $teamName],
        ]);
    }

    /**
     * Assign or update members in a team.
     *
     * @param  array<int, array{user_id: int, role_in_team?: string|null}>  $members
     */
    public function assignMembers(Team $team, array $members, User $user): Team
    {
        return DB::transaction(function () use ($team, $members, $user) {
            foreach ($members as $member) {
                $team->members()->syncWithoutDetaching([
                    $member['user_id'] => [
                        'role_in_team' => $member['role_in_team'] ?? 'Member',
                    ],
                ]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Project->value,
                'action' => 'team_members_assigned',
                'description' => "Assigned members to team: {$team->name}",
                'payload' => [
                    'team_id' => $team->id,
                    'user_ids' => array_column($members, 'user_id'),
                ],
            ]);

            return $team->load(['creator', 'members'])->loadCount(['members', 'projects']);
        });
    }

    /**
     * Remove a member from a team.
     */
    public function removeMember(Team $team, int $userId, User $user): Team
    {
        $team->members()->detach($userId);

        AuditLog::create([
            'user_id' => $user->id,
            'category' => AuditLogCategory::Project->value,
            'action' => 'team_member_removed',
            'description' => "Removed user ID {$userId} from team: {$team->name}",
            'payload' => ['team_id' => $team->id, 'removed_user_id' => $userId],
        ]);

        return $team->load(['creator', 'members'])->loadCount(['members', 'projects']);
    }
}
