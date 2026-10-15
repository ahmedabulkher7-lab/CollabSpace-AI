<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view any teams.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('teams.view');
    }

    /**
     * Determine whether the user can view the specific team.
     */
    public function view(User $user, Team $team): bool
    {
        if ($user->can('teams.view')) {
            return true;
        }

        return $team->members()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create teams.
     */
    public function create(User $user): bool
    {
        return $user->can('teams.create');
    }

    /**
     * Determine whether the user can update the team.
     */
    public function update(User $user, Team $team): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return $user->can('teams.update') && $team->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        return $user->can('teams.delete');
    }

    /**
     * Determine whether the user can assign members to the team.
     */
    public function assignMembers(User $user, Team $team): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return $user->can('teams.assign-members') && $team->created_by === $user->id;
    }
}
