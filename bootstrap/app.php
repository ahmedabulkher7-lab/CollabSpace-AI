<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 1. Validation Exceptions (422) - Preserves errors dictionary for front-end forms
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 2. Authentication Exceptions (401)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please provide a valid Bearer token.',
                    'errors' => null,
                ], 401);
            }
        });

        // 3. Authorization & Access Denied Exceptions (403)
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'You do not have permission to perform this action.',
                    'errors' => null,
                ], 403);
            }
        });

        // 4. Model & Resource Not Found Exceptions (404)
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $message = $e instanceof ModelNotFoundException
                    ? class_basename($e->getModel()).' not found.'
                    : ($e->getMessage() ?: 'The requested resource or endpoint was not found.');

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => null,
                ], 404);
            }
        });

        // 5. Catch-all Server & Runtime Exceptions (500)
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $message = app()->environment('production')
                    ? 'An unexpected error occurred. Please try again later.'
                    : $e->getMessage();

                return response()->json([
                    'success' => false,
                    'message' => $message ?: 'Server error.',
                    'errors' => null,
                ], $status >= 400 && $status < 600 ? $status : 500);
            }
        });
    })->create();
