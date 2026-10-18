<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole(UserRole::Administrator->value);
    }

    public function test_user_can_list_their_notifications_with_unread_count(): void
    {
        $task = Task::factory()->create();
        $this->user->notify(new TaskAssignedNotification($task, $task->project, $task->creator));

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.type', 'task_assigned');
    }

    public function test_user_cannot_see_other_users_notifications(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole(UserRole::TeamMember->value);
        $task = Task::factory()->create();

        $otherUser->notify(new TaskAssignedNotification($task, $task->project, $task->creator));

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.unread_count', 0);
    }

    public function test_user_can_filter_notifications_by_unread_status(): void
    {
        $task = Task::factory()->create();
        $this->user->notify(new TaskAssignedNotification($task, $task->project, $task->creator));
        $this->user->notify(new TaskAssignedNotification($task, $task->project, $task->creator));

        // Mark the first one as read
        $firstNotification = $this->user->notifications()->first();
        $firstNotification->markAsRead();

        // Query unread only
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications?status=unread');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_read', false);

        // Query read only
        $responseRead = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications?status=read');

        $responseRead->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_read', true);
    }

    public function test_user_can_get_unread_count_endpoint(): void
    {
        $task = Task::factory()->create();
        $this->user->notify(new TaskAssignedNotification($task, $task->project, $task->creator));
        $this->user->notify(new TaskAssignedNotification($task, $task->project, $task->creator));

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $task = Task::factory()->create();
        $this->user->notify(new TaskAssignedNotification($task, $task->project, $task->creator));
        $notification = $this->user->unreadNotifications()->first();

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $task = Task::factory()->create();
        $this->user->notify(new TaskAssignedNotification($task, $task->project, $task->creator));
        $this->user->notify(new TaskAssignedNotification($task, $task->project, $task->creator));

        $this->assertEquals(2, $this->user->unreadNotifications()->count());

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/notifications/mark-all-read');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.marked_count', 2);

        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }

    public function test_user_can_delete_notification(): void
    {
        $task = Task::factory()->create();
        $this->user->notify(new TaskAssignedNotification($task, $task->project, $task->creator));
        $notification = $this->user->notifications()->first();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_cannot_modify_or_delete_another_users_notification(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole(UserRole::TeamMember->value);
        $task = Task::factory()->create();
        $otherUser->notify(new TaskAssignedNotification($task, $task->project, $task->creator));
        $notification = $otherUser->notifications()->first();

        // Try marking as read
        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(404);

        // Try deleting
        $deleteResponse = $this->actingAs($this->user)
            ->deleteJson("/api/v1/notifications/{$notification->id}");

        $deleteResponse->assertStatus(404);
    }

    public function test_chat_mention_triggers_notification_for_mentioned_user(): void
    {
        $project = Project::factory()->create(['created_by' => $this->user->id]);
        $project->directMembers()->attach($this->user->id, ['role' => 'Owner']);

        $mentionedUser = User::factory()->create();
        $mentionedUser->assignRole(UserRole::TeamMember->value);
        $project->directMembers()->attach($mentionedUser->id, ['role' => 'Developer']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/projects/{$project->id}/messages", [
                'message' => 'Hey @Mentioned, take a look!',
                'mentions' => [$mentionedUser->id],
            ]);

        $response->assertStatus(201);

        // Assert mentioned user received notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $mentionedUser->id,
        ]);

        $notification = $mentionedUser->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertEquals('chat_mention', $notification->data['type']);
        $this->assertEquals($this->user->id, $notification->data['sender_id']);
    }

    public function test_task_assignment_triggers_notification_for_assignee(): void
    {
        $project = Project::factory()->create(['created_by' => $this->user->id]);
        $project->directMembers()->attach($this->user->id, ['role' => 'Owner']);

        $assignee = User::factory()->create();
        $assignee->assignRole(UserRole::TeamMember->value);
        $project->directMembers()->attach($assignee->id, ['role' => 'Developer']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/tasks', [
                'project_id' => $project->id,
                'name' => 'Implement Auth Middleware',
                'priority' => 'High',
                'status' => 'Pending',
                'assigned_to' => $assignee->id,
                'due_date' => now()->addDays(5)->toDateString(),
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $assignee->id,
        ]);

        $notification = $assignee->notifications()->first();
        $this->assertEquals('task_assigned', $notification->data['type']);
    }

    public function test_meeting_invitation_triggers_notification_for_participants(): void
    {
        $project = Project::factory()->create(['created_by' => $this->user->id]);
        $project->directMembers()->attach($this->user->id, ['role' => 'Owner']);

        $participant = User::factory()->create();
        $participant->assignRole(UserRole::TeamMember->value);
        $project->directMembers()->attach($participant->id, ['role' => 'Developer']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/meetings', [
                'project_id' => $project->id,
                'title' => 'Sprint Planning Kickoff',
                'meeting_date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '11:00',
                'meeting_link' => 'https://meet.google.com/abc-defg-hij',
                'participants' => [$participant->id],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $participant->id,
        ]);

        $notification = $participant->notifications()->first();
        $this->assertEquals('meeting_scheduled', $notification->data['type']);
    }
}
