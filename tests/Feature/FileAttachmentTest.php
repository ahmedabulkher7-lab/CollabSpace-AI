<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FileAttachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $pm;

    protected User $member;

    protected Project $project;

    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->assignRole(UserRole::Administrator->value);

        $this->pm = User::factory()->create();
        $this->pm->assignRole(UserRole::ProjectManager->value);

        $this->member = User::factory()->create();
        $this->member->assignRole(UserRole::TeamMember->value);

        $team = Team::factory()->create(['created_by' => $this->pm->id]);
        $team->members()->attach($this->member->id, ['role_in_team' => 'Frontend Dev']);

        $this->project = Project::factory()->create(['created_by' => $this->pm->id]);
        $this->project->teams()->attach($team->id);
        $this->project->directMembers()->attach($this->member->id, ['role' => 'Engineer']);

        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->member->id,
            'created_by' => $this->pm->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_files(): void
    {
        $response = $this->getJson('/api/v1/files');
        $response->assertStatus(401);
    }

    public function test_user_can_list_files_with_filters(): void
    {
        FileAttachment::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->pm->id,
        ]);

        $response = $this->actingAs($this->member)->getJson("/api/v1/files?project_id={$this->project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.data');
    }

    public function test_user_can_upload_file_to_project(): void
    {
        $fakeFile = UploadedFile::fake()->create('database_schema.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->pm)->postJson('/api/v1/files', [
            'file' => $fakeFile,
            'project_id' => $this->project->id,
            'name' => 'Database Architecture Spec',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Database Architecture Spec')
            ->assertJsonPath('data.project_id', $this->project->id)
            ->assertJsonPath('data.mime_type', 'application/pdf');

        $this->assertDatabaseHas('file_attachments', [
            'name' => 'Database Architecture Spec',
            'project_id' => $this->project->id,
            'uploaded_by' => $this->pm->id,
        ]);

        $attachment = FileAttachment::first();
        Storage::disk('public')->assertExists($attachment->file_path);
    }

    public function test_user_can_upload_file_to_task(): void
    {
        $fakeImage = UploadedFile::fake()->image('mockup.png', 800, 600);

        $response = $this->actingAs($this->member)->postJson('/api/v1/files', [
            'file' => $fakeImage,
            'task_id' => $this->task->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.task_id', $this->task->id)
            ->assertJsonPath('data.project_id', $this->project->id);

        $this->assertDatabaseHas('file_attachments', [
            'task_id' => $this->task->id,
            'project_id' => $this->project->id,
            'uploaded_by' => $this->member->id,
        ]);
    }

    public function test_user_can_view_file_details(): void
    {
        $attachment = FileAttachment::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->pm->id,
        ]);

        $response = $this->actingAs($this->member)->getJson("/api/v1/files/{$attachment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $attachment->id)
            ->assertJsonPath('data.project.id', $this->project->id);
    }

    public function test_user_can_download_file(): void
    {
        $content = 'Sample confidential file contents for testing.';
        $path = 'attachments/test_file.txt';
        Storage::disk('public')->put($path, $content);

        $attachment = FileAttachment::factory()->create([
            'name' => 'test_file.txt',
            'file_path' => $path,
            'project_id' => $this->project->id,
            'uploaded_by' => $this->pm->id,
        ]);

        $response = $this->actingAs($this->member)->get("/api/v1/files/{$attachment->id}/download");

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_user_can_delete_file(): void
    {
        $path = 'attachments/to_delete.pdf';
        Storage::disk('public')->put($path, 'dummy binary');

        $attachment = FileAttachment::factory()->create([
            'file_path' => $path,
            'project_id' => $this->project->id,
            'uploaded_by' => $this->pm->id,
        ]);

        Storage::disk('public')->assertExists($path);

        $response = $this->actingAs($this->pm)->deleteJson("/api/v1/files/{$attachment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('file_attachments', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_unauthorized_user_cannot_delete_file(): void
    {
        $attachment = FileAttachment::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->pm->id,
        ]);

        $otherUser = User::factory()->create();
        $otherUser->assignRole(UserRole::TeamMember->value);

        $response = $this->actingAs($otherUser)->deleteJson("/api/v1/files/{$attachment->id}");
        $response->assertStatus(403);
    }
}
