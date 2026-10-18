<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantTest extends TestCase
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
            'name' => 'CollabSpace Backend',
            'progress_percentage' => 75.0,
        ]);
        $this->project->directMembers()->attach($this->user->id, ['role' => 'Owner']);
    }

    public function test_unauthenticated_user_cannot_ask_assistant(): void
    {
        $response = $this->postJson('/api/v1/ai/ask', [
            'query' => 'What is the sprint status?',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_ask_general_workspace_question(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/ask', [
                'query' => 'Can you give me a summary of my workspace?',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'query',
                    'answer',
                    'project_id',
                    'context_summary' => [
                        'projects_count',
                        'tasks_count',
                        'overdue_tasks_count',
                        'upcoming_meetings_count',
                    ],
                    'suggested_actions',
                ],
            ]);
    }

    public function test_user_can_ask_about_overdue_tasks(): void
    {
        Task::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'Refactor Auth Tokens',
            'status' => TaskStatus::Pending->value,
            'due_date' => Carbon::now()->subDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/ask', [
                'query' => 'Are there any overdue tasks in the project?',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.context_summary.overdue_tasks_count', 1);

        $this->assertStringContainsString('overdue', strtolower($response->json('data.answer')));
    }

    public function test_user_can_ask_about_sprint_progress(): void
    {
        Task::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'Database Migration',
            'status' => TaskStatus::Completed->value,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/ask', [
                'query' => 'What is our current progress and completion rate?',
                'project_id' => $this->project->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.project_id', $this->project->id);

        $this->assertStringContainsString('CollabSpace Backend', $response->json('data.answer'));
    }

    public function test_user_can_ask_about_meetings(): void
    {
        Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Weekly Sync Meeting',
            'meeting_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:00',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/ask', [
                'query' => 'Do we have any upcoming meetings scheduled?',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.context_summary.upcoming_meetings_count', 1);

        $this->assertStringContainsString('Weekly Sync Meeting', $response->json('data.answer'));
    }

    public function test_ask_validation_requires_query(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai/ask', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }
}
