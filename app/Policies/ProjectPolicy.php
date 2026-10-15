<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('projects.view') || $user->can('projects.view-all');
    }

    /**
     * Determine whether the user can view the specific project.
     */
    public function view(User $user, Project $project): bool
    {
        return $project->hasAccess($user);
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return $user->can('projects.update') && $project->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return $user->can('projects.delete') && $project->created_by === $user->id;
    }
}
