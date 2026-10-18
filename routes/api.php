<?php

use App\Http\Controllers\Api\V1\AiAssistantController;
use App\Http\Controllers\Api\V1\AiSettingController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectMessageController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - CollabSpace AI v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Authentication & Profile Endpoints
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        // Social Authentication (Google, Facebook, LinkedIn)
        Route::prefix('social/{provider}')->group(function () {
            Route::get('/redirect', [SocialAuthController::class, 'redirect']);
            Route::match(['get', 'post'], '/callback', [SocialAuthController::class, 'callback']);
        });

        // Protected Auth Endpoints
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [ProfileController::class, 'me']);
            Route::put('/profile', [ProfileController::class, 'update']);
            Route::put('/password', [ProfileController::class, 'changePassword']);
            Route::get('/profile/tasks', [ProfileController::class, 'tasks']);
        });
    });

    // Protected Domain Modules
    Route::middleware('auth:sanctum')->group(function () {
        // Broadcasting Authentication for WebSockets (Sanctum)
        Broadcast::routes();

        // Teams Module
        Route::apiResource('teams', TeamController::class);
        Route::post('teams/{team}/members', [TeamController::class, 'assignMembers']);
        Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);

        // Projects Module
        Route::apiResource('projects', ProjectController::class);
        Route::get('projects/{project}/overview', [ProjectController::class, 'overview']);
        Route::get('projects/{project}/teams', [ProjectController::class, 'teams']);
        Route::post('projects/{project}/teams', [ProjectController::class, 'assignTeams']);
        Route::delete('projects/{project}/teams/{team}', [ProjectController::class, 'removeTeam']);

        // Tasks & Kanban Module
        Route::get('projects/{project}/tasks/kanban', [TaskController::class, 'kanban']);
        Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
        Route::patch('tasks/{task}/assign', [TaskController::class, 'assign']);
        Route::apiResource('tasks', TaskController::class);

        // Meetings & AI Summaries Module
        Route::patch('meetings/{meeting}/attendance', [MeetingController::class, 'markAttendance']);
        Route::post('meetings/{meeting}/summary/generate', [MeetingController::class, 'generateSummary']);
        Route::get('meetings/{meeting}/summary', [MeetingController::class, 'showSummary']);
        Route::put('meetings/{meeting}/summary', [MeetingController::class, 'updateSummary']);
        Route::post('meetings/{meeting}/summary/create-tasks', [MeetingController::class, 'createTasksFromSummary']);
        Route::apiResource('meetings', MeetingController::class);

        // File Management Module
        Route::get('files/{file}/download', [FileController::class, 'download']);
        Route::apiResource('files', FileController::class);

        // Real-Time Project Chat Module
        Route::get('projects/{project}/messages', [ProjectMessageController::class, 'index']);
        Route::post('projects/{project}/messages', [ProjectMessageController::class, 'store']);
        Route::put('messages/{message}', [ProjectMessageController::class, 'update']);
        Route::delete('messages/{message}', [ProjectMessageController::class, 'destroy']);
        Route::post('projects/{project}/messages/read', [ProjectMessageController::class, 'markRead']);
        Route::post('projects/{project}/messages/mark-read', [ProjectMessageController::class, 'markRead']);
        Route::get('projects/{project}/messages/unread-count', [ProjectMessageController::class, 'unreadCount']);

        // Notifications Module
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);

        // Dashboard & AI Assistant Module
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::post('ai/ask', [AiAssistantController::class, 'ask']);

        // Reports Module
        Route::get('reports/projects', [ReportController::class, 'projects']);
        Route::get('reports/tasks', [ReportController::class, 'tasks']);
        Route::get('reports/teams', [ReportController::class, 'teams']);
        Route::get('reports/users/{id?}', [ReportController::class, 'users']);

        // User Management Module
        Route::apiResource('users', UserController::class);

        // System & AI Configuration
        Route::get('ai/settings', [AiSettingController::class, 'index']);
        Route::put('ai/settings', [AiSettingController::class, 'update']);

        // Audit Logs (Compliance & Security)
        Route::get('audit-logs', [AuditLogController::class, 'index']);
    });

});
