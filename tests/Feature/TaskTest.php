<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
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

class TaskTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $pm;

    protected User $member;

    protected Project $project;

    protected Team $team;

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

        $this->team = Team::factory()->create(['created_by' => $this->pm->id]);
        $this->team->members()->attach($this->member->id, ['role_in_team' => 'Frontend Dev']);

        $this->project = Project::factory()->create([
            'created_by' => $this->pm->id,
            'progress_percentage' => 0.0,
        ]);
        $this->project->teams()->attach($this->team->id);
        $this->project->directMembers()->attach($this->member->id, ['role' => 'Engineer']);
    }

    public function test_unauthenticated_user_cannot_access_tasks(): void
    {
        $response = $this->getJson('/api/v1/tasks');
        $response->assertStatus(401);
    }

    public function test_user_can_list_tasks_with_filtering_and_pagination(): void
    {
        Task::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::Pending->value,
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::Completed->value,
        ]);

        $response = $this->actingAs($this->pm)->getJson('/api/v1/tasks?status=Pending');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.data');
    }

    public function test_user_can_retrieve_kanban_board_for_project(): void
    {
        Task::factory()->create([
            'name' => 'Todo Task',
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::Pending->value,
        ]);

        Task::factory()->create([
            'name' => 'In Progress Task',
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::InProgress->value,
        ]);

        Task::factory()->create([
            'name' => 'Completed Task',
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::Completed->value,
            'completed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->pm)->getJson("/api/v1/projects/{$this->project->id}/tasks/kanban");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_tasks', 3)
            ->assertJsonPath('data.columns.Pending.count', 1)
            ->assertJsonPath('data.columns.In Progress.count', 1)
            ->assertJsonPath('data.columns.Completed.count', 1)
            ->assertJsonStructure([
                'data' => [
                    'project' => ['id', 'name', 'progress_percentage'],
                    'total_tasks',
                    'columns' => [
                        'Pending' => ['name', 'count', 'tasks'],
                        'In Progress' => ['name', 'count', 'tasks'],
                        'In Review' => ['name', 'count', 'tasks'],
                        'Completed' => ['name', 'count', 'tasks'],
                    ],
                ],
            ]);
    }

    public function test_pm_can_create_task_and_recalculates_project_progress(): void
    {
        $payload = [
            'name' => 'Develop Authentication APIs',
            'description' => 'Build login, register and social auth endpoints with JWT.',
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
            'assigned_to' => $this->member->id,
            'priority' => TaskPriority::High->value,
            'status' => TaskStatus::Pending->value,
            'due_date' => Carbon::now()->addDays(5)->toDateString(),
        ];

        $response = $this->actingAs($this->pm)->postJson('/api/v1/tasks', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Develop Authentication APIs')
            ->assertJsonPath('data.priority', TaskPriority::High->value)
            ->assertJsonPath('data.status', TaskStatus::Pending->value)
            ->assertJsonPath('data.assignee.id', $this->member->id);

        $this->assertDatabaseHas('tasks', [
            'name' => 'Develop Authentication APIs',
            'project_id' => $this->project->id,
            'assigned_to' => $this->member->id,
        ]);
    }

    public function test_user_can_view_task_details(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
            'assigned_to' => $this->member->id,
            'created_by' => $this->pm->id,
        ]);

        $response = $this->actingAs($this->member)->getJson("/api/v1/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.project.id', $this->project->id)
            ->assertJsonPath('data.team.id', $this->team->id)
            ->assertJsonPath('data.assignee.id', $this->member->id);
    }

    public function test_pm_can_update_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'priority' => TaskPriority::Low->value,
        ]);

        $payload = [
            'name' => 'Refactored Task Name',
            'priority' => TaskPriority::Critical->value,
            'status' => TaskStatus::InProgress->value,
        ];

        $response = $this->actingAs($this->pm)->putJson("/api/v1/tasks/{$task->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Refactored Task Name')
            ->assertJsonPath('data.priority', TaskPriority::Critical->value)
            ->assertJsonPath('data.status', TaskStatus::InProgress->value);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'name' => 'Refactored Task Name',
            'priority' => TaskPriority::Critical->value,
        ]);
    }

    public function test_team_member_can_update_task_status_for_assigned_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->member->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::Pending->value,
        ]);

        $response = $this->actingAs($this->member)->patchJson("/api/v1/tasks/{$task->id}/status", [
            'status' => TaskStatus::InProgress->value,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', TaskStatus::InProgress->value);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::InProgress->value,
        ]);
    }

    public function test_completing_task_recalculates_project_progress(): void
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::Pending->value,
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::Pending->value,
        ]);

        // Complete task1
        $response = $this->actingAs($this->pm)->patchJson("/api/v1/tasks/{$task1->id}/status", [
            'status' => TaskStatus::Completed->value,
        ]);

        $response->assertStatus(200);

        // Project progress should be 50.0%
        $this->project->refresh();
        $this->assertEquals(50.0, (float) $this->project->progress_percentage);

        $task1->refresh();
        $this->assertNotNull($task1->completed_at);
    }

    public function test_pm_can_assign_task_to_user_and_team(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'assigned_to' => null,
            'team_id' => null,
        ]);

        $response = $this->actingAs($this->pm)->patchJson("/api/v1/tasks/{$task->id}/assign", [
            'assigned_to' => $this->member->id,
            'team_id' => $this->team->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.assigned_to', $this->member->id)
            ->assertJsonPath('data.team_id', $this->team->id);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assigned_to' => $this->member->id,
            'team_id' => $this->team->id,
        ]);
    }

    public function test_pm_can_delete_task_and_recalculates_project_progress(): void
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::Completed->value,
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'status' => TaskStatus::Pending->value,
        ]);

        $this->project->updateProgress();
        $this->assertEquals(50.0, (float) $this->project->fresh()->progress_percentage);

        // Delete the pending task -> now only completed task remains, progress becomes 100%
        $response = $this->actingAs($this->pm)->deleteJson("/api/v1/tasks/{$task2->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tasks', ['id' => $task2->id]);
        $this->assertEquals(100.0, (float) $this->project->fresh()->progress_percentage);
    }

    public function test_unauthorized_user_cannot_delete_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
        ]);

        $otherMember = User::factory()->create();
        $otherMember->assignRole(UserRole::TeamMember->value);

        $response = $this->actingAs($otherMember)->deleteJson("/api/v1/tasks/{$task->id}");
        $response->assertStatus(403);
    }
}
