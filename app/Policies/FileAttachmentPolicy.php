<?php

namespace App\Policies;

use App\Models\FileAttachment;
use App\Models\User;

class FileAttachmentPolicy
{
    /**
     * Determine whether the user can view any files.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('files.view') || $user->hasRole('Administrator');
    }

    /**
     * Determine whether the user can view the specific file.
     */
    public function view(User $user, FileAttachment $file): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        if ($file->uploaded_by === $user->id) {
            return true;
        }

        if ($file->project && $file->project->hasAccess($user)) {
            return true;
        }

        if ($file->task && $file->task->project->hasAccess($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can upload files.
     */
    public function upload(User $user): bool
    {
        return $user->can('files.upload') || $user->hasRole('Administrator');
    }

    /**
     * Determine whether the user can delete the file.
     */
    public function delete(User $user, FileAttachment $file): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        if ($file->uploaded_by === $user->id) {
            return true;
        }

        return $user->can('files.delete') && $file->project && $file->project->created_by === $user->id;
    }
}
