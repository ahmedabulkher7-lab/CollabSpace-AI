<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;

class ProjectMessagePolicy
{
    /**
     * Determine whether the user can view messages of the project.
     */
    public function viewAny(User $user, Project $project): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return $project->hasAccess($user);
    }

    /**
     * Determine whether the user can send messages in the project.
     */
    public function send(User $user, Project $project): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return $project->hasAccess($user) && $user->can('chat.send-message');
    }

    /**
     * Determine whether the user can update the message.
     */
    public function update(User $user, ProjectMessage $message): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return $message->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the message.
     */
    public function delete(User $user, ProjectMessage $message): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        if ($message->user_id === $user->id) {
            return true;
        }

        return $user->can('chat.manage-messages') && $message->project->created_by === $user->id;
    }
}
