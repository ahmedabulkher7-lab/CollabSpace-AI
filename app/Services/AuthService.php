<?php

namespace App\Services;

use App\Enums\AuditLogCategory;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Register a new user and generate a Sanctum access token.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        return DB::transaction(function () use ($data, $ip, $userAgent) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => 'active',
            ]);

            // Assign default role
            $user->assignRole(UserRole::TeamMember->value);

            // Audit log
            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Auth->value,
                'action' => 'user_registered',
                'description' => 'User registered a new account.',
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'payload' => ['email' => $user->email],
            ]);

            $token = $user->createToken('collabspace_auth_token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
            ];
        });
    }

    /**
     * Authenticate user credentials and generate a Sanctum token.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password, bool $remember = false, ?string $ip = null, ?string $userAgent = null): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact an administrator.'],
            ]);
        }

        AuditLog::create([
            'user_id' => $user->id,
            'category' => AuditLogCategory::Auth->value,
            'action' => 'user_login',
            'description' => 'User logged in successfully.',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'payload' => ['email' => $user->email, 'remember' => $remember],
        ]);

        $token = $user->createToken('collabspace_auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Logout user by revoking the current Sanctum token.
     */
    public function logout(User $user, ?string $ip = null, ?string $userAgent = null): void
    {
        $user->currentAccessToken()?->delete();

        AuditLog::create([
            'user_id' => $user->id,
            'category' => AuditLogCategory::Auth->value,
            'action' => 'user_logout',
            'description' => 'User logged out.',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Handle OAuth social media login / registration for Google, Facebook, LinkedIn.
     *
     * @return array{user: User, token: string}
     */
    public function handleSocialUser(string $provider, object $socialUser, ?string $ip = null, ?string $userAgent = null): array
    {
        $providerField = match (strtolower($provider)) {
            'google' => 'google_id',
            'facebook' => 'facebook_id',
            'linkedin', 'linkedin-openid' => 'linkedin_id',
            default => throw new \InvalidArgumentException("Unsupported social provider [{$provider}]"),
        };

        return DB::transaction(function () use ($provider, $providerField, $socialUser, $ip, $userAgent) {
            $user = User::where($providerField, $socialUser->getId())
                ->orWhere('email', $socialUser->getEmail())
                ->first();

            $isNew = false;

            if ($user) {
                // Link social ID and avatar if not present
                $updates = [];
                if (empty($user->{$providerField})) {
                    $updates[$providerField] = $socialUser->getId();
                }
                if (empty($user->avatar_url) && ! empty($socialUser->getAvatar())) {
                    $updates['avatar_url'] = $socialUser->getAvatar();
                }
                if (! empty($updates)) {
                    $user->update($updates);
                }
            } else {
                $isNew = true;
                $user = User::create([
                    'name' => $socialUser->getName() ?: 'User',
                    'email' => $socialUser->getEmail(),
                    $providerField => $socialUser->getId(),
                    'avatar_url' => $socialUser->getAvatar(),
                    'password' => Hash::make(Str::random(32)),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                $user->assignRole(UserRole::TeamMember->value);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Auth->value,
                'action' => $isNew ? 'social_register' : 'social_login',
                'description' => "User authenticated via {$provider}.",
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'payload' => ['provider' => $provider, 'email' => $user->email],
            ]);

            $token = $user->createToken('collabspace_auth_token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
            ];
        });
    }

    /**
     * Update user profile information.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        AuditLog::create([
            'user_id' => $user->id,
            'category' => AuditLogCategory::Auth->value,
            'action' => 'profile_updated',
            'description' => 'User updated profile information.',
            'payload' => array_keys($data),
        ]);

        return $user->fresh();
    }

    /**
     * Change user password.
     *
     *
     * @throws ValidationException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'category' => AuditLogCategory::Auth->value,
            'action' => 'password_changed',
            'description' => 'User changed account password.',
        ]);
    }

    /**
     * Generate password reset token.
     */
    public function sendResetLink(string $email): string
    {
        $user = User::where('email', $email)->firstOrFail();
        $token = Password::createToken($user);

        AuditLog::create([
            'user_id' => $user->id,
            'category' => AuditLogCategory::Auth->value,
            'action' => 'password_reset_requested',
            'description' => 'Password reset requested.',
            'payload' => ['email' => $email],
        ]);

        return $token;
    }

    /**
     * Reset password using token.
     *
     *
     * @throws ValidationException
     */
    public function resetPassword(string $email, string $token, string $password): void
    {
        $status = Password::reset(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $password, 'token' => $token],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                AuditLog::create([
                    'user_id' => $user->id,
                    'category' => AuditLogCategory::Auth->value,
                    'action' => 'password_reset_completed',
                    'description' => 'Password successfully reset via token.',
                    'payload' => ['email' => $user->email],
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }
}
