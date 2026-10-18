<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\MessageSent;
use App\Models\FileAttachment;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $pm;

    protected User $member;

    protected User $outsider;

    protected Project $project;

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

        $this->outsider = User::factory()->create();
        $this->outsider->assignRole(UserRole::TeamMember->value);

        $team = Team::factory()->create(['created_by' => $this->pm->id]);
        $team->members()->attach($this->member->id, ['role_in_team' => 'Frontend Dev']);

        $this->project = Project::factory()->create(['created_by' => $this->pm->id]);
        $this->project->teams()->attach($team->id);
        $this->project->directMembers()->attach($this->member->id, ['role' => 'Engineer']);
    }

    public function test_unauthenticated_user_cannot_access_chat(): void
    {
        $response = $this->getJson("/api/v1/projects/{$this->project->id}/messages");
        $response->assertStatus(401);
    }

    public function test_non_project_member_cannot_access_project_chat(): void
    {
        $response = $this->actingAs($this->outsider)->getJson("/api/v1/projects/{$this->project->id}/messages");
        $response->assertStatus(403);
    }

    public function test_project_member_can_list_messages(): void
    {
        ProjectMessage::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->pm->id,
        ]);

        $response = $this->actingAs($this->member)->getJson("/api/v1/projects/{$this->project->id}/messages");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.data');
    }

    public function test_project_member_can_send_message_and_broadcasts_event(): void
    {
        Event::fake([MessageSent::class]);

        $payload = [
            'message' => 'Hey team, I just pushed the new database indexing optimizations.',
        ];

        $response = $this->actingAs($this->member)->postJson("/api/v1/projects/{$this->project->id}/messages", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', $payload['message'])
            ->assertJsonPath('data.sender.id', $this->member->id)
            ->assertJsonPath('data.is_read_by_me', true);

        $this->assertDatabaseHas('project_messages', [
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
            'message' => $payload['message'],
        ]);

        $this->assertDatabaseHas('message_reads', [
            'user_id' => $this->member->id,
        ]);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($payload) {
            return $event->message->message === $payload['message']
                && $event->message->project_id === $this->project->id;
        });
    }

    public function test_member_can_reply_to_message_thread(): void
    {
        $rootMessage = ProjectMessage::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->pm->id,
            'message' => 'When can we expect the QA testing results?',
        ]);

        $payload = [
            'message' => 'Results will be ready today by 4:00 PM.',
            'parent_id' => $rootMessage->id,
        ];

        $response = $this->actingAs($this->member)->postJson("/api/v1/projects/{$this->project->id}/messages", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.parent.id', $rootMessage->id)
            ->assertJsonPath('data.parent.message', $rootMessage->message);

        $this->assertDatabaseHas('project_messages', [
            'parent_id' => $rootMessage->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_member_can_send_message_with_mentions(): void
    {
        $payload = [
            'message' => 'Please review the PR @Alex.',
            'mentions' => [$this->member->id],
        ];

        $response = $this->actingAs($this->pm)->postJson("/api/v1/projects/{$this->project->id}/messages", $payload);

        $response->assertStatus(201)
            ->assertJsonCount(1, 'data.mentions')
            ->assertJsonPath('data.mentions.0.id', $this->member->id);

        $this->assertDatabaseHas('message_mentions', [
            'user_id' => $this->member->id,
        ]);
    }

    public function test_member_can_send_message_with_file_attachment(): void
    {
        $attachment = FileAttachment::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->pm->id,
        ]);

        $payload = [
            'message' => 'Here is the architecture blueprint.',
            'file_attachment_id' => $attachment->id,
        ];

        $response = $this->actingAs($this->pm)->postJson("/api/v1/projects/{$this->project->id}/messages", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.attachment.id', $attachment->id);

        $this->assertDatabaseHas('project_messages', [
            'file_attachment_id' => $attachment->id,
        ]);
    }

    public function test_author_can_edit_own_message(): void
    {
        $message = ProjectMessage::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
            'message' => 'Typo text here.',
        ]);

        $response = $this->actingAs($this->member)->putJson("/api/v1/messages/{$message->id}", [
            'message' => 'Corrected text after edit.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Corrected text after edit.')
            ->assertJsonPath('data.is_edited', true);

        $this->assertDatabaseHas('project_messages', [
            'id' => $message->id,
            'message' => 'Corrected text after edit.',
            'is_edited' => true,
        ]);
    }

    public function test_author_can_delete_own_message(): void
    {
        $message = ProjectMessage::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
        ]);

        $response = $this->actingAs($this->member)->deleteJson("/api/v1/messages/{$message->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('project_messages', ['id' => $message->id]);
    }

    public function test_member_can_mark_messages_as_read_and_check_unread_count(): void
    {
        $message = ProjectMessage::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->pm->id,
            'message' => 'Unread announcement.',
        ]);

        // Check unread count is 1 for member
        $countResponse = $this->actingAs($this->member)->getJson("/api/v1/projects/{$this->project->id}/messages/unread-count");
        $countResponse->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);

        // Mark as read
        $readResponse = $this->actingAs($this->member)->postJson("/api/v1/projects/{$this->project->id}/messages/read", [
            'message_id' => $message->id,
        ]);
        $readResponse->assertStatus(200);

        // Check unread count is now 0
        $countResponse2 = $this->actingAs($this->member)->getJson("/api/v1/projects/{$this->project->id}/messages/unread-count");
        $countResponse2->assertStatus(200)
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_unauthorized_user_cannot_edit_another_users_message(): void
    {
        $message = ProjectMessage::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->pm->id,
        ]);

        $response = $this->actingAs($this->member)->putJson("/api/v1/messages/{$message->id}", [
            'message' => 'Hacked message.',
        ]);

        $response->assertStatus(403);
    }
}
