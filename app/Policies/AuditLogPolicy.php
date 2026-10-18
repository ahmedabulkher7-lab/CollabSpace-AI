<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determine whether the user can view audit logs.
     * Allowed only for Administrators and Project Managers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Administrator') || $user->hasRole('Project Manager');
    }

    /**
     * Determine whether the user can view a specific audit log.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole('Administrator') || $user->hasRole('Project Manager');
    }
}
