<?php

namespace Database\Seeders;

use App\Enums\AuditLogCategory;
use App\Enums\MeetingStatus;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\AiSetting;
use App\Models\AuditLog;
use App\Models\FileAttachment;
use App\Models\Meeting;
use App\Models\MeetingSummary;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Roles & Permissions
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. Seed Users
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@collabspace.ai',
            'password' => Hash::make('password123'),
            'job_title' => 'Chief Technology Officer',
            'years_of_experience' => 12,
            'phone' => '+201000000001',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(UserRole::Administrator->value);

        $pm = User::create([
            'name' => 'Sarah Connor',
            'email' => 'pm@collabspace.ai',
            'password' => Hash::make('password123'),
            'job_title' => 'Lead Project Manager',
            'years_of_experience' => 8,
            'phone' => '+201000000002',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $pm->assignRole(UserRole::ProjectManager->value);

        $frontendDev = User::create([
            'name' => 'Alex Rivera',
            'email' => 'frontend@collabspace.ai',
            'password' => Hash::make('password123'),
            'job_title' => 'Senior Frontend Engineer',
            'years_of_experience' => 5,
            'phone' => '+201000000003',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $frontendDev->assignRole(UserRole::TeamMember->value);

        $backendDev = User::create([
            'name' => 'Tarek Mansour',
            'email' => 'backend@collabspace.ai',
            'password' => Hash::make('password123'),
            'job_title' => 'Senior Backend Engineer',
            'years_of_experience' => 6,
            'phone' => '+201000000004',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $backendDev->assignRole(UserRole::TeamMember->value);

        $uiuxDesigner = User::create([
            'name' => 'Elena Rostova',
            'email' => 'designer@collabspace.ai',
            'password' => Hash::make('password123'),
            'job_title' => 'Product Designer (UI/UX)',
            'years_of_experience' => 4,
            'phone' => '+201000000005',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $uiuxDesigner->assignRole(UserRole::TeamMember->value);

        $qaEngineer = User::create([
            'name' => 'Omar Farooq',
            'email' => 'qa@collabspace.ai',
            'password' => Hash::make('password123'),
            'job_title' => 'Lead Quality Assurance Engineer',
            'years_of_experience' => 5,
            'phone' => '+201000000006',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $qaEngineer->assignRole(UserRole::TeamMember->value);

        // 3. Seed Teams
        $backendTeam = Team::create([
            'name' => 'Backend Team',
            'description' => 'Responsible for core APIs, microservices, and database architecture.',
            'created_by' => $admin->id,
        ]);
        $backendTeam->members()->attach([
            $backendDev->id => ['role_in_team' => 'Lead Engineer'],
            $pm->id => ['role_in_team' => 'Product Owner'],
        ]);

        $frontendTeam = Team::create([
            'name' => 'Frontend Team',
            'description' => 'Responsible for web and mobile client interfaces.',
            'created_by' => $admin->id,
        ]);
        $frontendTeam->members()->attach([
            $frontendDev->id => ['role_in_team' => 'Lead Engineer'],
            $uiuxDesigner->id => ['role_in_team' => 'Design Partner'],
        ]);

        $qaTeam = Team::create([
            'name' => 'QA Team',
            'description' => 'Automated testing, quality gates, and release sign-offs.',
            'created_by' => $admin->id,
        ]);
        $qaTeam->members()->attach([
            $qaEngineer->id => ['role_in_team' => 'Lead QA'],
        ]);

        // 4. Seed Projects
        $project1 = Project::create([
            'name' => 'CollabSpace AI Web & Mobile',
            'description' => 'Building the next-generation collaboration platform with real-time AI assistance.',
            'start_date' => Carbon::now()->subDays(15),
            'deadline' => Carbon::now()->addDays(45),
            'priority' => ProjectPriority::Critical->value,
            'status' => ProjectStatus::InProgress->value,
            'progress_percentage' => 45.00,
            'created_by' => $pm->id,
        ]);
        $project1->teams()->attach([$backendTeam->id, $frontendTeam->id, $qaTeam->id]);
        $project1->directMembers()->attach([$pm->id, $backendDev->id, $frontendDev->id, $uiuxDesigner->id, $qaEngineer->id]);

        $project2 = Project::create([
            'name' => 'Cloud Migration & Infrastructure Overhaul',
            'description' => 'Migrating high-throughput database workloads to automated multi-region clusters.',
            'start_date' => Carbon::now()->subDays(5),
            'deadline' => Carbon::now()->addDays(30),
            'priority' => ProjectPriority::High->value,
            'status' => ProjectStatus::InProgress->value,
            'progress_percentage' => 20.00,
            'created_by' => $admin->id,
        ]);
        $project2->teams()->attach([$backendTeam->id]);
        $project2->directMembers()->attach([$admin->id, $backendDev->id]);

        // 5. Seed Tasks
        $task1 = Task::create([
            'name' => 'Design System & Figma Component Library',
            'description' => 'Standardize color palettes, typography, and button components in Figma.',
            'project_id' => $project1->id,
            'team_id' => $frontendTeam->id,
            'assigned_to' => $uiuxDesigner->id,
            'created_by' => $pm->id,
            'priority' => TaskPriority::High->value,
            'status' => TaskStatus::Completed->value,
            'due_date' => Carbon::now()->subDays(3),
            'completed_at' => Carbon::now()->subDays(2),
        ]);

        $task2 = Task::create([
            'name' => 'Implement JWT & Sanctum API Authentication',
            'description' => 'Build secure login, registration, and role-based permissions middleware.',
            'project_id' => $project1->id,
            'team_id' => $backendTeam->id,
            'assigned_to' => $backendDev->id,
            'created_by' => $pm->id,
            'priority' => TaskPriority::Critical->value,
            'status' => TaskStatus::Completed->value,
            'due_date' => Carbon::now()->subDays(1),
            'completed_at' => Carbon::now()->subHours(10),
        ]);

        $task3 = Task::create([
            'name' => 'Build Real-Time Chat WebSocket Channels',
            'description' => 'Integrate event broadcasting for real-time messaging, typing indicators, and read receipts.',
            'project_id' => $project1->id,
            'team_id' => $backendTeam->id,
            'assigned_to' => $backendDev->id,
            'created_by' => $pm->id,
            'priority' => TaskPriority::High->value,
            'status' => TaskStatus::InProgress->value,
            'due_date' => Carbon::now()->addDays(4),
        ]);

        $task4 = Task::create([
            'name' => 'Automated Integration Testing for Tasks APIs',
            'description' => 'Write Pest/PHPUnit tests covering project and task life cycles.',
            'project_id' => $project1->id,
            'team_id' => $qaTeam->id,
            'assigned_to' => $qaEngineer->id,
            'created_by' => $pm->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::InReview->value,
            'due_date' => Carbon::now()->addDays(2),
        ]);

        $task5 = Task::create([
            'name' => 'Frontend Dashboard & Analytics Integration',
            'description' => 'Connect UI components with backend statistics and progress calculation endpoints.',
            'project_id' => $project1->id,
            'team_id' => $frontendTeam->id,
            'assigned_to' => $frontendDev->id,
            'created_by' => $pm->id,
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Pending->value,
            'due_date' => Carbon::now()->addDays(7),
        ]);

        // 6. Seed File Attachments
        $file1 = FileAttachment::create([
            'name' => 'CollabSpace_SRS_v1.0.pdf',
            'file_path' => 'attachments/CollabSpace_SRS_v1.0.pdf',
            'disk' => 'public',
            'file_size' => 1048576,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $pm->id,
            'project_id' => $project1->id,
            'task_id' => null,
        ]);

        $file2 = FileAttachment::create([
            'name' => 'Figma_UI_Components_Spec.png',
            'file_path' => 'attachments/Figma_UI_Components_Spec.png',
            'disk' => 'public',
            'file_size' => 524288,
            'mime_type' => 'image/png',
            'uploaded_by' => $uiuxDesigner->id,
            'project_id' => $project1->id,
            'task_id' => $task1->id,
        ]);

        // 7. Seed Project Chat Messages
        $msg1 = ProjectMessage::create([
            'project_id' => $project1->id,
            'user_id' => $pm->id,
            'message' => 'Welcome team to the CollabSpace AI project sprint! Let us align on task deadlines.',
        ]);

        $msg2 = ProjectMessage::create([
            'project_id' => $project1->id,
            'user_id' => $backendDev->id,
            'parent_id' => $msg1->id,
            'message' => 'Backend authentication and roles setup is completed and verified against the SRS specs.',
        ]);

        $msg2->readers()->attach([
            $pm->id => ['read_at' => now()],
            $frontendDev->id => ['read_at' => now()],
        ]);

        // 8. Seed Meetings & AI Summaries
        $meeting1 = Meeting::create([
            'title' => 'Sprint Planning & Architecture Sync',
            'description' => 'Review API schema, database relationships, and AI assistant capabilities.',
            'meeting_date' => Carbon::now()->subDays(1)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'meeting_link' => 'https://meet.google.com/collabspace-sync',
            'project_id' => $project1->id,
            'created_by' => $pm->id,
            'status' => MeetingStatus::Completed->value,
        ]);

        $meeting1->participants()->attach([
            $pm->id => ['attended' => true],
            $backendDev->id => ['attended' => true],
            $frontendDev->id => ['attended' => true],
            $uiuxDesigner->id => ['attended' => true],
        ]);

        MeetingSummary::create([
            'meeting_id' => $meeting1->id,
            'summary' => 'The team reviewed the architecture specification for CollabSpace AI. Backend confirmed Sanctum tokens, Spatie RBAC, and granular Policies. Frontend and UI/UX agreed on component specifications.',
            'decisions_made' => [
                'Adopt modular Action/Service layer for all domain operations.',
                'Enforce strict Form Request validations and API Resources for JSON responses.',
                'Integrate Google Gemini AI for smart meeting summaries and project insights.',
            ],
            'action_items' => [
                'Complete task CRUD and real-time broadcasting by Friday.',
                'Prepare automated Feature tests with at least 80% coverage.',
            ],
            'assigned_tasks' => [
                'Tarek: Real-time chat endpoints',
                'Omar: Automated tests for meeting and project workflows',
            ],
            'risks' => [
                'Rate limits on third-party AI provider during concurrent meeting summary generation.',
            ],
            'next_steps' => [
                'Review progress on Saturday noon standup.',
            ],
            'generated_by_ai' => true,
        ]);

        $meeting2 = Meeting::create([
            'title' => 'Design & Deliverables Final Review',
            'description' => 'Final walkthrough before competition submission.',
            'meeting_date' => Carbon::now()->addDays(2)->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'meeting_link' => 'https://meet.google.com/collabspace-review',
            'project_id' => $project1->id,
            'created_by' => $pm->id,
            'status' => MeetingStatus::Scheduled->value,
        ]);
        $meeting2->participants()->attach([$pm->id, $backendDev->id, $frontendDev->id]);

        // 9. Seed Audit Logs
        AuditLog::create([
            'user_id' => $admin->id,
            'category' => AuditLogCategory::Auth->value,
            'action' => 'user_login',
            'description' => 'Administrator logged in from dashboard.',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'payload' => ['email' => 'admin@collabspace.ai'],
        ]);

        AuditLog::create([
            'user_id' => $pm->id,
            'category' => AuditLogCategory::Project->value,
            'action' => 'project_created',
            'description' => 'Created project: CollabSpace AI Web & Mobile',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'payload' => ['project_id' => $project1->id, 'name' => $project1->name],
        ]);

        // 10. Seed Default AI Settings (Configured by Administrator)
        AiSetting::create([
            'key' => 'ai_provider',
            'value' => 'gemini',
            'type' => 'string',
            'description' => 'Primary AI model provider for CollabSpace',
            'updated_by' => $admin->id,
        ]);

        AiSetting::create([
            'key' => 'ai_model',
            'value' => 'gemini-1.5-pro',
            'type' => 'string',
            'description' => 'Active LLM model version',
            'updated_by' => $admin->id,
        ]);

        AiSetting::create([
            'key' => 'ai_temperature',
            'value' => '0.7',
            'type' => 'string',
            'description' => 'AI creativity and variability temperature',
            'updated_by' => $admin->id,
        ]);

        AiSetting::create([
            'key' => 'ai_features_enabled',
            'value' => json_encode(['meeting_summary', 'project_insights', 'task_recommendations', 'smart_search']),
            'type' => 'json',
            'description' => 'Active AI features in platform',
            'updated_by' => $admin->id,
        ]);

        AiSetting::create([
            'key' => 'ai_enabled',
            'value' => '1',
            'type' => 'boolean',
            'description' => 'Global toggle for AI Assistant capabilities',
            'updated_by' => $admin->id,
        ]);
    }
}
