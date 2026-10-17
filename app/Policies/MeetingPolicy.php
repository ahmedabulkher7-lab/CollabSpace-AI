<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    /**
     * Determine whether the user can view any meetings.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('meetings.view') || $user->hasRole('Administrator');
    }

    /**
     * Determine whether the user can view the specific meeting.
     */
    public function view(User $user, Meeting $meeting): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        return $meeting->project->hasAccess($user);
    }

    /**
     * Determine whether the user can create meetings.
     */
    public function create(User $user): bool
    {
        return $user->can('meetings.create') || $user->hasRole('Administrator');
    }

    /**
     * Determine whether the user can update the meeting.
     */
    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        if ($meeting->created_by === $user->id) {
            return true;
        }

        return $user->can('meetings.update') && $meeting->project->hasAccess($user);
    }

    /**
     * Determine whether the user can delete the meeting.
     */
    public function delete(User $user, Meeting $meeting): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        if ($meeting->created_by === $user->id) {
            return true;
        }

        return $user->can('meetings.delete') && $meeting->project->created_by === $user->id;
    }
}
