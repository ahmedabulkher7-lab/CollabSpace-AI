<?php

namespace Tests\Feature;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
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

    public function test_pm_can_create_project_with_teams_and_members(): void
    {
        $team = Team::factory()->create();

        $payload = [
            'name' => 'NextGen Mobile App',
            'description' => 'Cross-platform app built with Flutter and Laravel.',
            'start_date' => Carbon::now()->toDateString(),
            'deadline' => Carbon::now()->addDays(30)->toDateString(),
            'priority' => ProjectPriority::High->value,
            'status' => ProjectStatus::InProgress->value,
            'teams' => [$team->id],
            'members' => [
                [
                    'user_id' => $this->member->id,
                    'role' => 'Frontend Lead',
                ],
            ],
        ];

        $response = $this->actingAs($this->pm)->postJson('/api/v1/projects', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'NextGen Mobile App')
            ->assertJsonPath('data.priority', ProjectPriority::High->value)
            ->assertJsonPath('data.status', ProjectStatus::InProgress->value);

        $this->assertDatabaseHas('projects', [
            'name' => 'NextGen Mobile App',
            'created_by' => $this->pm->id,
        ]);

        $this->assertDatabaseHas('project_team', [
            'team_id' => $team->id,
        ]);

        $this->assertDatabaseHas('project_user', [
            'user_id' => $this->member->id,
            'role' => 'Frontend Lead',
        ]);
    }

    public function test_regular_team_member_cannot_create_project(): void
    {
        $payload = [
            'name' => 'Unauthorized Project',
            'start_date' => Carbon::now()->toDateString(),
            'deadline' => Carbon::now()->addDays(10)->toDateString(),
        ];

        $response = $this->actingAs($this->member)->postJson('/api/v1/projects', $payload);

        $response->assertStatus(403);
    }

    public function test_can_list_projects_with_filters_and_search(): void
    {
        $project1 = Project::factory()->create([
            'name' => 'FinTech Wallet API',
            'status' => ProjectStatus::InProgress->value,
            'created_by' => $this->pm->id,
        ]);

        $project2 = Project::factory()->create([
            'name' => 'Healthcare Web Portal',
            'status' => ProjectStatus::Completed->value,
            'created_by' => $this->pm->id,
        ]);

        // Search filter
        $response = $this->actingAs($this->admin)->getJson('/api/v1/projects?search=FinTech');
        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.name', 'FinTech Wallet API');

        // Status filter
        $responseStatus = $this->actingAs($this->admin)->getJson('/api/v1/projects?status=Completed');
        $responseStatus->assertStatus(200)
            ->assertJsonPath('data.data.0.name', 'Healthcare Web Portal');
    }

    public function test_can_get_project_overview_with_statistics(): void
    {
        $project = Project::factory()->create([
            'name' => 'Alpha Platform',
            'created_by' => $this->pm->id,
        ]);

        Task::create([
            'name' => 'Task 1',
            'project_id' => $project->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::Completed->value,
        ]);

        Task::create([
            'name' => 'Task 2',
            'project_id' => $project->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::Pending->value,
        ]);

        $response = $this->actingAs($this->pm)->getJson("/api/v1/projects/{$project->id}/overview");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.name', 'Alpha Platform')
            ->assertJsonPath('data.tasks_statistics.total', 2)
            ->assertJsonPath('data.tasks_statistics.completed', 1)
            ->assertJsonPath('data.tasks_statistics.pending', 1)
            ->assertJsonPath('data.tasks_statistics.completion_rate', 50);
    }

    public function test_can_get_project_teams_with_member_task_ratios(): void
    {
        $project = Project::factory()->create([
            'name' => 'Alpha',
            'created_by' => $this->pm->id,
        ]);

        $uiuxTeam = Team::create([
            'name' => 'UI/UX',
            'created_by' => $this->pm->id,
        ]);
        $project->teams()->attach($uiuxTeam->id);

        $designer = User::factory()->create();
        $uiuxTeam->members()->attach($designer->id, ['role_in_team' => 'Lead Designer']);

        // Create 20 tasks: 12 completed, 8 pending for designer
        for ($i = 0; $i < 12; $i++) {
            Task::create([
                'name' => "Completed Task {$i}",
                'project_id' => $project->id,
                'team_id' => $uiuxTeam->id,
                'assigned_to' => $designer->id,
                'created_by' => $this->pm->id,
                'status' => TaskStatus::Completed->value,
            ]);
        }
        for ($j = 0; $j < 8; $j++) {
            Task::create([
                'name' => "Pending Task {$j}",
                'project_id' => $project->id,
                'team_id' => $uiuxTeam->id,
                'assigned_to' => $designer->id,
                'created_by' => $this->pm->id,
                'status' => TaskStatus::Pending->value,
            ]);
        }

        $response = $this->actingAs($this->pm)->getJson("/api/v1/projects/{$project->id}/teams");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'UI/UX')
            ->assertJsonPath('data.0.total_tasks', 20)
            ->assertJsonPath('data.0.completed_tasks', 12)
            ->assertJsonPath('data.0.completion_percentage', 60)
            ->assertJsonPath('data.0.members.0.name', $designer->name)
            ->assertJsonPath('data.0.members.0.role_in_team', 'Lead Designer')
            ->assertJsonPath('data.0.members.0.tasks_ratio', '12/20')
            ->assertJsonPath('data.0.members.0.tasks_completed', 12)
            ->assertJsonPath('data.0.members.0.tasks_total', 20);
    }

    public function test_pm_can_update_project(): void
    {
        $project = Project::factory()->create([
            'created_by' => $this->pm->id,
            'name' => 'Initial Project Name',
        ]);

        $response = $this->actingAs($this->pm)->putJson("/api/v1/projects/{$project->id}", [
            'name' => 'Updated Project Name',
            'priority' => ProjectPriority::Critical->value,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Project Name')
            ->assertJsonPath('data.priority', ProjectPriority::Critical->value);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'priority' => ProjectPriority::Critical->value,
        ]);
    }

    public function test_can_assign_teams_to_project(): void
    {
        $project = Project::factory()->create(['created_by' => $this->pm->id]);
        $newTeam = Team::factory()->create();

        $response = $this->actingAs($this->pm)->postJson("/api/v1/projects/{$project->id}/teams", [
            'team_ids' => [$newTeam->id],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('project_team', [
            'project_id' => $project->id,
            'team_id' => $newTeam->id,
        ]);
    }

    public function test_can_remove_team_from_project(): void
    {
        $project = Project::factory()->create(['created_by' => $this->pm->id]);
        $team = Team::factory()->create();
        $project->teams()->attach($team->id);

        $response = $this->actingAs($this->pm)->deleteJson("/api/v1/projects/{$project->id}/teams/{$team->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('project_team', [
            'project_id' => $project->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_admin_can_delete_project(): void
    {
        $project = Project::factory()->create(['created_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_regular_member_cannot_delete_project(): void
    {
        $project = Project::factory()->create(['created_by' => $this->pm->id]);

        $response = $this->actingAs($this->member)->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(403);
    }
}
