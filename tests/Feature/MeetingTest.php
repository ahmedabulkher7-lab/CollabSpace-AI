<?php

namespace Tests\Feature;

use App\Enums\MeetingStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\MeetingSummary;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_meetings(): void
    {
        $response = $this->getJson('/api/v1/meetings');
        $response->assertStatus(401);
    }

    public function test_user_can_list_meetings_with_filters(): void
    {
        Meeting::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'meeting_date' => Carbon::now()->addDays(2)->toDateString(),
            'status' => MeetingStatus::Scheduled->value,
        ]);

        Meeting::factory()->completed()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'meeting_date' => Carbon::now()->subDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($this->pm)->getJson('/api/v1/meetings?time_filter=upcoming');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.data');
    }

    public function test_pm_can_schedule_meeting_with_participants(): void
    {
        $payload = [
            'title' => 'Sprint 3 Kickoff & Architecture Review',
            'description' => 'Discuss API endpoints, DB indexing, and frontend deliverable dates.',
            'meeting_date' => Carbon::now()->addDays(3)->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '15:30:00',
            'meeting_link' => 'https://meet.google.com/abc-xyz-qwe',
            'project_id' => $this->project->id,
            'participants' => [$this->member->id],
        ];

        $response = $this->actingAs($this->pm)->postJson('/api/v1/meetings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Sprint 3 Kickoff & Architecture Review')
            ->assertJsonPath('data.status', MeetingStatus::Scheduled->value)
            ->assertJsonPath('data.project.id', $this->project->id)
            ->assertJsonCount(1, 'data.participants');

        $this->assertDatabaseHas('meetings', [
            'title' => 'Sprint 3 Kickoff & Architecture Review',
            'project_id' => $this->project->id,
        ]);

        $this->assertDatabaseHas('meeting_user', [
            'user_id' => $this->member->id,
            'attended' => false,
        ]);
    }

    public function test_user_can_view_meeting_details(): void
    {
        $meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
        ]);
        $meeting->participants()->attach($this->member->id, ['attended' => false]);

        $response = $this->actingAs($this->member)->getJson("/api/v1/meetings/{$meeting->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $meeting->id)
            ->assertJsonPath('data.project.id', $this->project->id)
            ->assertJsonPath('data.creator.id', $this->pm->id);
    }

    public function test_pm_can_update_meeting_and_reschedule(): void
    {
        $meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
        ]);

        $newDate = Carbon::now()->addDays(5)->toDateString();

        $response = $this->actingAs($this->pm)->putJson("/api/v1/meetings/{$meeting->id}", [
            'title' => 'Rescheduled Sprint Review',
            'meeting_date' => $newDate,
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'meeting_link' => 'https://meet.google.com/new-link-123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Rescheduled Sprint Review')
            ->assertJsonPath('data.meeting_date', $newDate);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'title' => 'Rescheduled Sprint Review',
        ]);
        $this->assertEquals($newDate, $meeting->fresh()->meeting_date->toDateString());
    }

    public function test_pm_can_mark_attendance_for_participants(): void
    {
        $meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
        ]);
        $meeting->participants()->attach($this->member->id, ['attended' => false]);

        $response = $this->actingAs($this->pm)->patchJson("/api/v1/meetings/{$meeting->id}/attendance", [
            'attendance' => [
                [
                    'user_id' => $this->member->id,
                    'attended' => true,
                ],
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('meeting_user', [
            'meeting_id' => $meeting->id,
            'user_id' => $this->member->id,
            'attended' => true,
        ]);
    }

    public function test_pm_can_generate_ai_meeting_summary(): void
    {
        $meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
            'status' => MeetingStatus::Scheduled->value,
        ]);
        $meeting->participants()->attach($this->member->id);

        $transcript = 'Tarek presented the API database indexing benchmark. Alex proposed finalizing the Figma UI components by Wednesday. The team decided to adopt MySQL 8.0 with automated migrations. Next meeting is scheduled for Monday.';

        $response = $this->actingAs($this->pm)->postJson("/api/v1/meetings/{$meeting->id}/summary/generate", [
            'transcript' => $transcript,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meeting_id', $meeting->id)
            ->assertJsonPath('data.generated_by_ai', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'meeting_id',
                    'summary',
                    'decisions_made',
                    'action_items',
                    'risks',
                    'next_steps',
                ],
            ]);

        $this->assertDatabaseHas('meeting_summaries', [
            'meeting_id' => $meeting->id,
            'generated_by_ai' => true,
        ]);

        // Meeting status should automatically become Completed
        $this->assertEquals(MeetingStatus::Completed->value, $meeting->fresh()->status->value);

        // Ai interaction should be recorded with tokens
        $this->assertDatabaseHas('ai_interactions', [
            'user_id' => $this->pm->id,
            'project_id' => $this->project->id,
            'type' => 'meeting_summary',
        ]);
    }

    public function test_user_can_view_meeting_summary(): void
    {
        $meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
        ]);
        MeetingSummary::factory()->create(['meeting_id' => $meeting->id]);

        $response = $this->actingAs($this->member)->getJson("/api/v1/meetings/{$meeting->id}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meeting_id', $meeting->id);
    }

    public function test_pm_can_update_meeting_summary_manually(): void
    {
        $meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
        ]);
        $summary = MeetingSummary::factory()->create(['meeting_id' => $meeting->id]);

        $response = $this->actingAs($this->pm)->putJson("/api/v1/meetings/{$meeting->id}/summary", [
            'summary' => 'Refined executive summary after PM review.',
            'decisions_made' => ['Deploy on Laravel Cloud with managed Redis.'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.summary', 'Refined executive summary after PM review.');

        $this->assertDatabaseHas('meeting_summaries', [
            'id' => $summary->id,
            'summary' => 'Refined executive summary after PM review.',
        ]);
    }

    public function test_pm_can_create_tasks_from_ai_summary_action_items(): void
    {
        $meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
        ]);
        $meeting->participants()->attach($this->member->id);

        MeetingSummary::factory()->create([
            'meeting_id' => $meeting->id,
            'action_items' => [
                [
                    'title' => 'Build Socialite OAuth callback handlers',
                    'suggested_assignee' => $this->member->name,
                    'priority' => TaskPriority::High->value,
                ],
                [
                    'title' => 'Write comprehensive Pest feature tests',
                    'suggested_assignee' => null,
                    'priority' => TaskPriority::Medium->value,
                ],
            ],
        ]);

        $response = $this->actingAs($this->pm)->postJson("/api/v1/meetings/{$meeting->id}/summary/create-tasks", [
            'default_priority' => TaskPriority::Medium->value,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.tasks_count', 2);

        $this->assertDatabaseHas('tasks', [
            'name' => 'Build Socialite OAuth callback handlers',
            'project_id' => $this->project->id,
            'assigned_to' => $this->member->id,
            'priority' => TaskPriority::High->value,
            'status' => TaskStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('tasks', [
            'name' => 'Write comprehensive Pest feature tests',
            'project_id' => $this->project->id,
            'priority' => TaskPriority::Medium->value,
        ]);
    }

    public function test_pm_can_delete_meeting(): void
    {
        $meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
        ]);

        $response = $this->actingAs($this->pm)->deleteJson("/api/v1/meetings/{$meeting->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
    }

    public function test_unauthorized_user_cannot_delete_meeting(): void
    {
        $meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->pm->id,
        ]);

        $otherUser = User::factory()->create();
        $otherUser->assignRole(UserRole::TeamMember->value);

        $response = $this->actingAs($otherUser)->deleteJson("/api/v1/meetings/{$meeting->id}");
        $response->assertStatus(403);
    }
}
