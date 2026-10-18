<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\FileAttachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole(UserRole::Administrator->value);

        $this->project = Project::factory()->create([
            'created_by' => $this->user->id,
            'progress_percentage' => 50.0,
        ]);
        $this->project->directMembers()->attach($this->user->id, ['role' => 'Owner']);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/v1/dashboard');
        $response->assertStatus(401);
    }

    public function test_user_can_retrieve_dashboard_statistics(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'projects' => [
                        'total',
                        'active',
                        'completed',
                        'average_progress',
                    ],
                    'tasks' => [
                        'total',
                        'assigned_to_me',
                        'completed',
                        'overdue',
                        'by_status',
                        'by_priority',
                    ],
                    'meetings' => [
                        'upcoming_count',
                        'upcoming',
                    ],
                    'teams' => [
                        'total',
                    ],
                    'recent_activity',
                    'recent_files',
                ],
            ]);
    }

    public function test_dashboard_statistics_correctly_aggregates_projects_and_tasks(): void
    {
        // 1 completed task, 1 pending task
        Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->user->id,
            'status' => TaskStatus::Completed->value,
            'priority' => TaskPriority::High->value,
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->user->id,
            'status' => TaskStatus::Pending->value,
            'priority' => TaskPriority::Medium->value,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.projects.total', 1)
            ->assertJsonPath('data.tasks.total', 2)
            ->assertJsonPath('data.tasks.assigned_to_me', 2)
            ->assertJsonPath('data.tasks.completed', 1)
            ->assertJsonPath('data.tasks.by_status.Completed', 1)
            ->assertJsonPath('data.tasks.by_status.Pending', 1);
    }

    public function test_dashboard_displays_overdue_tasks_count(): void
    {
        Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => TaskStatus::Pending->value,
            'due_date' => Carbon::now()->subDays(3)->toDateString(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.tasks.overdue', 1);
    }

    public function test_dashboard_includes_recent_activity_and_files(): void
    {
        FileAttachment::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Architecture_Diagram.png',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.recent_files')
            ->assertJsonPath('data.recent_files.0.name', 'Architecture_Diagram.png');
    }
}
