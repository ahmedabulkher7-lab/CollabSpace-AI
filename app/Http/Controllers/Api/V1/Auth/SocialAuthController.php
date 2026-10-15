<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class SocialAuthController extends Controller
{
    use ApiResponse;

    /**
     * Supported OAuth providers.
     *
     * @var array<string>
     */
    protected array $supportedProviders = ['google', 'facebook', 'linkedin'];

    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Get OAuth authorization redirect URL for client apps.
     */
    public function redirect(string $provider): JsonResponse
    {
        $provider = strtolower($provider);

        if (! in_array($provider, $this->supportedProviders, true)) {
            return $this->errorResponse("Unsupported provider [{$provider}]. Allowed: ".implode(', ', $this->supportedProviders), 422);
        }

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);
        $targetUrl = $driver->stateless()->redirect()->getTargetUrl();

        return $this->successResponse([
            'provider' => $provider,
            'authorization_url' => $targetUrl,
        ], "Redirect URL for {$provider} generated successfully.");
    }

    /**
     * Handle OAuth callback from web redirect or client access token.
     */
    public function callback(string $provider, Request $request): JsonResponse
    {
        $provider = strtolower($provider);

        if (! in_array($provider, $this->supportedProviders, true)) {
            return $this->errorResponse("Unsupported provider [{$provider}]. Allowed: ".implode(', ', $this->supportedProviders), 422);
        }

        try {
            /** @var AbstractProvider $driver */
            $driver = Socialite::driver($provider)->stateless();

            // Check if client submitted an access token directly
            if ($token = $request->input('token')) {
                $socialUser = $driver->userFromToken($token);
            } else {
                $socialUser = $driver->user();
            }

            if (! $socialUser || ! $socialUser->getEmail()) {
                return $this->errorResponse("Could not retrieve user email from {$provider}.", 422);
            }

            $result = $this->authService->handleSocialUser(
                provider: $provider,
                socialUser: $socialUser,
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $this->successResponse([
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'token_type' => 'Bearer',
            ], "Successfully authenticated via {$provider}.");
        } catch (\Exception $e) {
            return $this->errorResponse("OAuth authentication failed: {$e->getMessage()}", 400);
        }
    }
}
