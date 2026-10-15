<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_can_register_successfully_and_receive_token_and_default_role(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'job_title' => 'Software Engineer',
            'years_of_experience' => 3,
            'phone' => '+201112223334',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'job_title',
                        'years_of_experience',
                        'roles',
                    ],
                    'token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'name' => 'John Doe',
        ]);

        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertTrue($user->hasRole(UserRole::TeamMember->value));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'category' => 'auth',
            'action' => 'user_registered',
        ]);
    }

    public function test_registration_validation_fails_for_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'valid@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole(UserRole::TeamMember->value);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'valid@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'email', 'roles'],
                    'token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'category' => 'auth',
            'action' => 'user_login',
        ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'valid@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'valid@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::TeamMember->value);

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'job_title' => 'Junior Developer',
            'years_of_experience' => 1,
        ]);
        $user->assignRole(UserRole::TeamMember->value);

        $response = $this->actingAs($user)->putJson('/api/v1/auth/profile', [
            'job_title' => 'Mid-level Developer',
            'years_of_experience' => 3,
            'phone' => '+201099999999',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.job_title', 'Mid-level Developer')
            ->assertJsonPath('data.years_of_experience', 3);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'job_title' => 'Mid-level Developer',
        ]);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword123'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/v1/auth/password', [
            'current_password' => 'oldpassword123',
            'password' => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword456', $user->password));
    }

    public function test_user_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $this->assertCount(0, $user->tokens);
    }

    public function test_forgot_password_generates_token(): void
    {
        $user = User::factory()->create(['email' => 'recover@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'recover@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['reset_token'],
            ]);
    }

    public function test_social_auth_redirect_returns_authorization_url(): void
    {
        $response = $this->getJson('/api/v1/auth/social/google/redirect');

        $response->assertStatus(200)
            ->assertJsonPath('data.provider', 'google')
            ->assertJsonStructure(['data' => ['authorization_url']]);
    }

    public function test_social_auth_callback_creates_and_authenticates_user(): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-unique-id-999');
        $socialiteUser->shouldReceive('getName')->andReturn('Google User');
        $socialiteUser->shouldReceive('getEmail')->andReturn('google.user@example.com');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

        $providerMock = Mockery::mock(GoogleProvider::class);
        $providerMock->shouldReceive('stateless')->andReturnSelf();
        $providerMock->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($providerMock);

        $response = $this->getJson('/api/v1/auth/social/google/callback');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'email', 'google_linked'],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'google.user@example.com',
            'google_id' => 'google-unique-id-999',
        ]);

        $user = User::where('email', 'google.user@example.com')->first();
        $this->assertTrue($user->hasRole(UserRole::TeamMember->value));
    }
}
