<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(
            data: $request->validated(),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Registration successful. Welcome to CollabSpace AI!', 201);
    }

    /**
     * Authenticate and obtain an access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->login(
            email: $validated['email'],
            password: $validated['password'],
            remember: $validated['remember'] ?? false,
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Logged in successfully.');
    }

    /**
     * Terminate the authenticated session.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout(
            user: $request->user(),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->successResponse(null, 'Logged out successfully.');
    }

    /**
     * Initiate forgot password flow.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $token = $this->authService->sendResetLink($request->validated('email'));

        return $this->successResponse([
            'reset_token' => $token,
        ], 'Password reset instructions have been generated.');
    }

    /**
     * Complete password reset flow.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->authService->resetPassword(
            email: $validated['email'],
            token: $validated['token'],
            password: $validated['password']
        );

        return $this->successResponse(null, 'Password has been reset successfully.');
    }
}
