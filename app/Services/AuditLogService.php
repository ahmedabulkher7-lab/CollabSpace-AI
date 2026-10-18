<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogService
{
    /**
     * Get paginated audit logs based on validated search and filter criteria.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedLogs(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::with('user:id,name,email,avatar_url');

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', 'like', "%{$filters['action']}%");
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('description', 'like', "%{$search}%");
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->latest('created_at')->paginate($perPage);
    }
}
