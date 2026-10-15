<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Get the authenticated user profile with roles, teams, and projects.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['teams', 'projects']);

        return $this->successResponse([
            'user' => new UserResource($user),
            'teams' => $user->teams,
            'projects' => $user->projects,
        ], 'Profile retrieved successfully.');
    }

    /**
     * Update user profile information.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile(
            user: $request->user(),
            data: $request->validated()
        );

        return $this->successResponse(
            new UserResource($user),
            'Profile updated successfully.'
        );
    }

    /**
     * Change user account password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            user: $request->user(),
            currentPassword: $request->validated('current_password'),
            newPassword: $request->validated('password')
        );

        return $this->successResponse(null, 'Password updated successfully.');
    }

    /**
     * Get user assigned tasks.
     */
    public function tasks(Request $request): JsonResponse
    {
        $tasks = $request->user()->assignedTasks()->with(['project', 'team'])->latest()->paginate(15);

        return $this->successResponse($tasks, 'Assigned tasks retrieved successfully.');
    }

    /**
     * Get user uploaded files.
     */
    public function files(Request $request): JsonResponse
    {
        $files = $request->user()->uploadedFiles()->with(['project', 'task'])->latest()->paginate(15);

        return $this->successResponse($files, 'Uploaded files retrieved successfully.');
    }
}
