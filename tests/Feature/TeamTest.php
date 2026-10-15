<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $pm;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(UserRole::Administrator->value);

        $this->pm = User::factory()->create();
        $this->pm->assignRole(UserRole::ProjectManager->value);

        $this->member = User::factory()->create();
        $this->member->assignRole(UserRole::TeamMember->value);
    }

    public function test_pm_can_create_team_with_initial_members(): void
    {
        $engineer = User::factory()->create();
        $engineer->assignRole(UserRole::TeamMember->value);

        $payload = [
            'name' => 'Mobile Dev Team',
            'description' => 'Dedicated flutter and native engineers.',
            'members' => [
                [
                    'user_id' => $engineer->id,
                    'role_in_team' => 'Lead Mobile Dev',
                ],
            ],
        ];

        $response = $this->actingAs($this->pm)->postJson('/api/v1/teams', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Mobile Dev Team')
            ->assertJsonPath('data.created_by', $this->pm->id);

        $this->assertDatabaseHas('teams', [
            'name' => 'Mobile Dev Team',
            'created_by' => $this->pm->id,
        ]);

        $this->assertDatabaseHas('team_user', [
            'user_id' => $engineer->id,
            'role_in_team' => 'Lead Mobile Dev',
        ]);

        // Creator automatically added as Leader
        $this->assertDatabaseHas('team_user', [
            'user_id' => $this->pm->id,
            'role_in_team' => 'Leader',
        ]);
    }

    public function test_regular_team_member_cannot_create_team(): void
    {
        $payload = [
            'name' => 'Unauthorized Team',
        ];

        $response = $this->actingAs($this->member)->postJson('/api/v1/teams', $payload);

        $response->assertStatus(403);
    }

    public function test_can_list_teams_with_pagination_and_counts(): void
    {
        Team::factory()->count(3)->create([
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->member)->getJson('/api/v1/teams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'members_count',
                            'active_projects_count',
                        ],
                    ],
                ],
            ]);
    }

    public function test_can_view_single_team_with_members(): void
    {
        $team = Team::create([
            'name' => 'QA Core',
            'created_by' => $this->admin->id,
        ]);
        $team->members()->attach($this->member->id, ['role_in_team' => 'Automation Engineer']);

        $response = $this->actingAs($this->member)->getJson("/api/v1/teams/{$team->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $team->id)
            ->assertJsonPath('data.name', 'QA Core')
            ->assertJsonPath('data.members.0.role_in_team', 'Automation Engineer');
    }

    public function test_pm_can_update_team(): void
    {
        $team = Team::create([
            'name' => 'Initial Name',
            'created_by' => $this->pm->id,
        ]);

        $response = $this->actingAs($this->pm)->putJson("/api/v1/teams/{$team->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description content.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_assign_members_to_team(): void
    {
        $team = Team::create([
            'name' => 'DevOps Pod',
            'created_by' => $this->pm->id,
        ]);

        $newMember = User::factory()->create();

        $response = $this->actingAs($this->pm)->postJson("/api/v1/teams/{$team->id}/members", [
            'members' => [
                [
                    'user_id' => $newMember->id,
                    'role_in_team' => 'Site Reliability Engineer',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $newMember->id,
            'role_in_team' => 'Site Reliability Engineer',
        ]);
    }

    public function test_can_remove_member_from_team(): void
    {
        $team = Team::create([
            'name' => 'Design System',
            'created_by' => $this->pm->id,
        ]);
        $team->members()->attach($this->member->id, ['role_in_team' => 'Designer']);

        $response = $this->actingAs($this->pm)->deleteJson("/api/v1/teams/{$team->id}/members/{$this->member->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('team_user', [
            'team_id' => $team->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_admin_can_delete_team(): void
    {
        $team = Team::create([
            'name' => 'Obsolete Team',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/teams/{$team->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_team_member_cannot_delete_team(): void
    {
        $team = Team::create([
            'name' => 'Protected Team',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->member)->deleteJson("/api/v1/teams/{$team->id}");

        $response->assertStatus(403);
    }
}
