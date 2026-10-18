<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuditLogController extends Controller
{
    /**
     * List paginated audit logs (Administrator & Project Manager only).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('Administrator') && ! $user->hasRole('Project Manager')) {
            throw new AccessDeniedHttpException('Only Administrators and Project Managers can view system audit logs.');
        }

        $query = AuditLog::with('user:id,name,email,avatar_url');

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->input('action')}%");
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('description', 'like', "%{$search}%");
        }

        $perPage = (int) $request->input('per_page', 20);
        $logs = $query->latest('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'category' => $log->category->value ?? $log->category,
                'action' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'payload' => $log->payload,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
