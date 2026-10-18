<?php

namespace App\Services;

use App\Enums\AuditLogCategory;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UserService
{
    /**
     * Get paginated users with filters and eager loading.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<User>
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with(['roles', 'permissions']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $role = $filters['role'];
            $query->whereHas('roles', fn (Builder $q) => $q->where('name', $role));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Create a new user with role assignment.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $roleName = $data['role'] ?? UserRole::TeamMember->value;
            unset($data['role']);

            $data['password'] = Hash::make($data['password']);
            $data['status'] = $data['status'] ?? 'active';

            /** @var User $user */
            $user = User::create($data);
            $user->assignRole($roleName);

            if ($actor) {
                AuditLog::create([
                    'user_id' => $actor->id,
                    'category' => AuditLogCategory::Auth->value,
                    'action' => 'user_created',
                    'description' => "Administrator created user '{$user->name}' with role '{$roleName}'.",
                    'payload' => [
                        'created_user_id' => $user->id,
                        'email' => $user->email,
                        'role' => $roleName,
                    ],
                ]);
            }

            return $user->load(['roles', 'permissions']);
        });
    }

    /**
     * Update an existing user.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
                unset($data['role']);
            }

            $user->update($data);

            if ($actor) {
                AuditLog::create([
                    'user_id' => $actor->id,
                    'category' => AuditLogCategory::Auth->value,
                    'action' => 'user_updated',
                    'description' => "User '{$user->name}' was updated by administrator.",
                    'payload' => array_keys($data),
                ]);
            }

            return $user->fresh(['roles', 'permissions']);
        });
    }

    /**
     * Delete a user.
     */
    public function delete(User $user, User $actor): bool
    {
        if ($user->id === $actor->id) {
            throw new BadRequestHttpException('Administrators cannot delete their own account.');
        }

        return DB::transaction(function () use ($user, $actor) {
            $userName = $user->name;
            $userId = $user->id;

            $deleted = $user->delete();

            AuditLog::create([
                'user_id' => $actor->id,
                'category' => AuditLogCategory::Auth->value,
                'action' => 'user_deleted',
                'description' => "User '{$userName}' was deleted by administrator.",
                'payload' => ['deleted_user_id' => $userId],
            ]);

            return (bool) $deleted;
        });
    }
}
