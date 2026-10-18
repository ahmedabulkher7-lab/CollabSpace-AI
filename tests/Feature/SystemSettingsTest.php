<?php

namespace Tests\Feature;

use App\Enums\AuditLogCategory;
use App\Enums\UserRole;
use App\Models\AiSetting;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(UserRole::Administrator->value);

        $this->member = User::factory()->create();
        $this->member->assignRole(UserRole::TeamMember->value);

        AiSetting::create([
            'key' => 'ai_provider',
            'value' => 'gemini',
            'type' => 'string',
            'description' => 'Primary AI provider',
        ]);
    }

    public function test_user_can_view_ai_settings(): void
    {
        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/ai/settings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.key', 'ai_provider')
            ->assertJsonPath('data.0.value', 'gemini');
    }

    public function test_admin_can_update_ai_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/ai/settings', [
                'settings' => [
                    [
                        'key' => 'ai_provider',
                        'value' => 'openai',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.updated_count', 1);

        $this->assertEquals('openai', AiSetting::where('key', 'ai_provider')->value('value'));
    }

    public function test_regular_member_cannot_update_ai_settings(): void
    {
        $response = $this->actingAs($this->member)
            ->putJson('/api/v1/ai/settings', [
                'settings' => [
                    [
                        'key' => 'ai_provider',
                        'value' => 'openai',
                    ],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_view_audit_logs(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'category' => AuditLogCategory::Auth->value,
            'action' => 'user_login',
            'description' => 'Admin logged in.',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'user_login');
    }

    public function test_regular_member_cannot_view_audit_logs(): void
    {
        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(403);
    }
}
