<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;
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

        // Protected Endpoints
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [ProfileController::class, 'me']);
            Route::put('/profile', [ProfileController::class, 'update']);
            Route::put('/password', [ProfileController::class, 'changePassword']);
            Route::get('/profile/tasks', [ProfileController::class, 'tasks']);
            Route::get('/profile/files', [ProfileController::class, 'files']);
        });
    });

});
