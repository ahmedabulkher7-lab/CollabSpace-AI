<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(UserRole::Administrator->value);

        $this->member = User::factory()->create();
        $this->member->assignRole(UserRole::TeamMember->value);
    }

    public function test_unauthenticated_user_cannot_access_users(): void
    {
        $response = $this->getJson('/api/v1/users');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_list_users(): void
    {
        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_user_with_role(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/users', [
                'name' => 'Kareem Mostafa',
                'email' => 'kareem@collabspace.ai',
                'password' => 'secret1234',
                'role' => UserRole::ProjectManager->value,
                'job_title' => 'Associate PM',
                'years_of_experience' => 3,
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Kareem Mostafa')
            ->assertJsonPath('data.roles.0', UserRole::ProjectManager->value);

        $this->assertDatabaseHas('users', ['email' => 'kareem@collabspace.ai']);
    }

    public function test_regular_member_cannot_create_user(): void
    {
        $response = $this->actingAs($this->member)
            ->postJson('/api/v1/users', [
                'name' => 'Intruder User',
                'email' => 'intruder@collabspace.ai',
                'password' => 'secret1234',
                'role' => UserRole::TeamMember->value,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_user_role_and_status(): void
    {
        $target = User::factory()->create();
        $target->assignRole(UserRole::TeamMember->value);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/users/{$target->id}", [
                'role' => UserRole::ProjectManager->value,
                'status' => 'inactive',
                'job_title' => 'Senior PM',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.roles.0', UserRole::ProjectManager->value)
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_admin_can_delete_user(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/users/{$target->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/users/{$this->admin->id}");

        $response->assertStatus(400);
    }
}
