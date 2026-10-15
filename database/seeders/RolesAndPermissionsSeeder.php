<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions
        $permissions = [
            // User management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Team management
            'teams.view',
            'teams.create',
            'teams.update',
            'teams.delete',
            'teams.assign-members',

            // Project management
            'projects.view',
            'projects.view-all',
            'projects.create',
            'projects.update',
            'projects.delete',

            // Task management
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.update-status',

            // File management
            'files.view',
            'files.upload',
            'files.delete',

            // Chat
            'chat.access',
            'chat.send-message',
            'chat.manage-messages',

            // Meetings
            'meetings.view',
            'meetings.create',
            'meetings.update',
            'meetings.delete',

            // Reports & Analytics
            'reports.view-all',
            'reports.view-personal',

            // AI & System Settings
            'ai.use',
            'ai.configure',
            'audit-logs.view',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        // Create Administrator Role
        $adminRole = Role::findOrCreate(UserRole::Administrator->value, 'web');
        $adminRole->syncPermissions(Permission::all());

        // Create Project Manager Role
        $pmRole = Role::findOrCreate(UserRole::ProjectManager->value, 'web');
        $pmRole->syncPermissions([
            'teams.view',
            'teams.create',
            'teams.update',
            'teams.assign-members',
            'projects.view',
            'projects.create',
            'projects.update',
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.update-status',
            'files.view',
            'files.upload',
            'files.delete',
            'chat.access',
            'chat.send-message',
            'meetings.view',
            'meetings.create',
            'meetings.update',
            'meetings.delete',
            'reports.view-all',
            'reports.view-personal',
            'ai.use',
        ]);

        // Create Team Member Role
        $memberRole = Role::findOrCreate(UserRole::TeamMember->value, 'web');
        $memberRole->syncPermissions([
            'teams.view',
            'projects.view',
            'tasks.view',
            'tasks.update-status',
            'files.view',
            'files.upload',
            'chat.access',
            'chat.send-message',
            'meetings.view',
            'reports.view-personal',
            'ai.use',
        ]);
    }
}
