<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\FileAttachment;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
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
            'status' => ProjectStatus::InProgress->value,
            'progress_percentage' => 50.0,
            'deadline' => Carbon::now()->addDays(20)->toDateString(),
        ]);
        $this->project->teams()->attach($this->team->id);
        $this->project->directMembers()->attach($this->member->id, ['role' => 'Engineer']);
    }

    public function test_unauthenticated_user_cannot_access_reports(): void
    {
        $response = $this->getJson('/api/v1/reports/projects');
        $response->assertStatus(401);
    }

    public function test_user_can_retrieve_project_reports(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/projects');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_projects',
                        'active_projects',
                        'delayed_projects',
                        'completed_projects',
                        'on_hold_projects',
                        'average_completion_rate',
                    ],
                    'projects',
                ],
            ]);
    }

    public function test_project_report_identifies_delayed_projects(): void
    {
        // Create an overdue/delayed project
        Project::factory()->create([
            'created_by' => $this->admin->id,
            'status' => ProjectStatus::InProgress->value,
            'deadline' => Carbon::now()->subDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/reports/projects');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.delayed_projects', 1);
    }

    public function test_user_can_retrieve_task_reports_with_breakdown(): void
    {
        Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->member->id,
            'status' => TaskStatus::Completed->value,
            'priority' => TaskPriority::High->value,
            'due_date' => Carbon::tomorrow()->toDateString(),
            'completed_at' => Carbon::now(),
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->member->id,
            'status' => TaskStatus::Pending->value,
            'priority' => TaskPriority::Low->value,
        ]);

        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/reports/tasks');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_tasks', 2)
            ->assertJsonPath('data.summary.completed_tasks', 1)
            ->assertJsonPath('data.summary.completion_rate', 50)
            ->assertJsonPath('data.by_priority.High', 1)
            ->assertJsonPath('data.by_priority.Low', 1);
    }

    public function test_task_report_filters_by_project(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->admin->id]);
        Task::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/reports/tasks?project_id={$otherProject->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_tasks', 1);
    }

    public function test_user_can_retrieve_team_performance_reports(): void
    {
        Task::factory()->create([
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
            'status' => TaskStatus::Completed->value,
        ]);

        $response = $this->actingAs($this->pm)
            ->getJson('/api/v1/reports/teams');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_teams',
                        'total_members',
                        'total_assigned_tasks',
                    ],
                    'teams',
                ],
            ])
            ->assertJsonPath('data.teams.0.team_id', $this->team->id)
            ->assertJsonPath('data.teams.0.total_tasks', 1);
    }

    public function test_user_can_retrieve_personal_productivity_report(): void
    {
        Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->member->id,
            'status' => TaskStatus::Completed->value,
            'due_date' => Carbon::now()->addDays(2)->toDateString(),
            'completed_at' => Carbon::now(),
        ]);

        $meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
        ]);
        $meeting->participants()->attach($this->member->id, ['attended' => true]);

        FileAttachment::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->member->id,
            'file_size' => 2048,
        ]);

        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/reports/users');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $this->member->id)
            ->assertJsonPath('data.tasks.completed', 1)
            ->assertJsonPath('data.meetings.attended', 1)
            ->assertJsonPath('data.files.uploaded_count', 1)
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'tasks',
                    'meetings',
                    'files',
                    'productivity' => [
                        'score',
                        'performance_level',
                    ],
                ],
            ]);
    }

    public function test_admin_or_pm_can_retrieve_another_users_report(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/reports/users/{$this->member->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', $this->member->id);

        $pmResponse = $this->actingAs($this->pm)
            ->getJson("/api/v1/reports/users/{$this->member->id}");

        $pmResponse->assertStatus(200)
            ->assertJsonPath('data.user.id', $this->member->id);
    }

    public function test_regular_member_cannot_view_another_users_report(): void
    {
        $otherMember = User::factory()->create();
        $otherMember->assignRole(UserRole::TeamMember->value);

        $response = $this->actingAs($this->member)
            ->getJson("/api/v1/reports/users/{$otherMember->id}");

        $response->assertStatus(403);
    }
}
