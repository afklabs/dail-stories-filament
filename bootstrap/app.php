<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            /*
            |--------------------------------------------------------------------------
            | Admin API Routes - Filament Integration
            |--------------------------------------------------------------------------
            |
            | These routes handle the admin panel API endpoints for Filament.
            | They're separated for better security, middleware, and performance.
            |
            */
            Route::middleware(['web', 'auth:sanctum'])
                ->prefix('admin/api')
                ->name('admin.api.')
                ->group(base_path('routes/admin_api.php'));

            /*
            |--------------------------------------------------------------------------
            | Additional Route Groups (if needed in future)
            |--------------------------------------------------------------------------
            |
            | You can add more route groups here as your application grows:
            | - Mobile API v2
            | - Webhook endpoints
            | - Public widgets
            | - Partner integrations
            |
            */

            // Example for future API versions
            // Route::middleware(['api', 'throttle:120,1'])
            //     ->prefix('api/v2')
            //     ->name('api.v2.')
            //     ->group(base_path('routes/api_v2.php'));

            // Example for webhook endpoints
            // Route::middleware(['api', 'throttle:60,1'])
            //     ->prefix('webhooks')
            //     ->name('webhooks.')
            //     ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
        |--------------------------------------------------------------------------
        | Global Middleware Configuration
        |--------------------------------------------------------------------------
        */

        // Global middleware for all routes
        $middleware->append([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // API-specific middleware
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Web-specific middleware
        $middleware->web(append: [
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Route-Specific Middleware Aliases
        |--------------------------------------------------------------------------
        */

        $middleware->alias([
            // Authentication middleware
            'auth.api' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'auth.admin' => \App\Http\Middleware\EnsureUserIsAdmin::class, // Create this

            // Security middleware
            'throttle.api' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':60,1',
            'throttle.admin' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':120,1',

            // Custom middleware
            'device.verification' => \App\Http\Middleware\VerifyDeviceId::class, // Create this
            'api.version' => \App\Http\Middleware\ApiVersionMiddleware::class, // Create this
        ]);

        /*
        |--------------------------------------------------------------------------
        | Priority Middleware (High Performance)
        |--------------------------------------------------------------------------
        */

        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /*
        |--------------------------------------------------------------------------
        | Exception Handling for APIs
        |--------------------------------------------------------------------------
        */

        // Custom API exception rendering
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->is('admin/api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Authentication exceptions
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->is('admin/api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'error' => 'Unauthenticated',
                ], 401);
            }
        });

        // Authorization exceptions
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->is('admin/api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                    'error' => 'Unauthorized',
                ], 403);
            }
        });

        // Database exceptions
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->is('admin/api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                    'error' => 'Not Found',
                ], 404);
            }
        });

        // Generic exceptions for API routes
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->is('admin/api/*')) {
                // Don't expose sensitive information in production
                $message = app()->environment('production')
                    ? 'Internal server error'
                    : $e->getMessage();

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'error' => 'Server Error',
                ], 500);
            }
        });
    })
    ->create();
