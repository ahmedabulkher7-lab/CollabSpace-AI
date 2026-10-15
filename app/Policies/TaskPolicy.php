<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any tasks.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view') || $user->hasRole('Administrator');
    }

    /**
     * Determine whether the user can view the specific task.
     */
    public function view(User $user, Task $task): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return $task->project->hasAccess($user);
    }

    /**
     * Determine whether the user can create tasks.
     */
    public function create(User $user): bool
    {
        return $user->can('tasks.create') || $user->hasRole('Administrator');
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return $user->can('tasks.update') && $task->project->hasAccess($user);
    }

    /**
     * Determine whether the user can update the task status.
     */
    public function updateStatus(User $user, Task $task): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        if ($user->can('tasks.update') && $task->project->hasAccess($user)) {
            return true;
        }

        // Assigned user can update status
        if ($task->assigned_to === $user->id) {
            return true;
        }

        return $user->can('tasks.update-status') && $task->project->hasAccess($user);
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        if (! $user->can('tasks.delete')) {
            return false;
        }

        return $task->created_by === $user->id || $task->project->created_by === $user->id;
    }
}
