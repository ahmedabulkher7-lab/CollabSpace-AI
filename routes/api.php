<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TeamController;
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
            Route::get('/profile/files', [ProfileController::class, 'files']);
        });
    });

    // Protected Domain Modules
    Route::middleware('auth:sanctum')->group(function () {
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
    });

});
