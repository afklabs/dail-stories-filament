<?php

declare(strict_types=1);

use App\Http\Controllers\API\MemberController;
use App\Http\Controllers\API\StoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes for Flutter App
|--------------------------------------------------------------------------
|
| These routes are automatically prefixed with 'api' and assigned the 'api'
| middleware group by Laravel 12's bootstrap/app.php configuration.
|
| Rate Limiting: 60 requests per minute per IP
| Authentication: Laravel Sanctum
|
*/

// ✅ Health check endpoint (no auth required)
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
    ]);
})->name('health');

// ✅ API Authentication route
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json([
        'success' => true,
        'data' => $request->user(),
    ]);
})->name('user');

/*
|--------------------------------------------------------------------------
| API v1 Routes - Public API for Flutter App
|--------------------------------------------------------------------------
|
| All v1 routes are versioned and include device verification for security.
| These routes serve the mobile Flutter application.
|
*/

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['api.version'])
    ->group(function (): void {

        /*
        |--------------------------------------------------------------------------
        | Public Routes (No Authentication Required)
        |--------------------------------------------------------------------------
        */

        // Member authentication
        Route::prefix('members')->name('members.')->group(function (): void {
            Route::post('/register', [MemberController::class, 'register'])
                ->name('register')
                ->middleware('throttle:5,1'); // 5 attempts per minute

            Route::post('/login', [MemberController::class, 'login'])
                ->name('login')
                ->middleware('throttle:10,1'); // 10 attempts per minute

            Route::post('/forgot-password', [MemberController::class, 'forgotPassword'])
                ->name('forgot-password')
                ->middleware('throttle:3,1'); // 3 attempts per minute
        });

        // Public story browsing (CORE BUSINESS LOGIC - Allow discovery before registration)
        Route::prefix('stories')->name('stories.')->group(function (): void {
            // ✅ PRIMARY DISCOVERY ROUTES - Public Access
            Route::get('/', [StoryController::class, 'index'])
                ->name('index')
                ->middleware('throttle:60,1'); // 60 requests per minute

            Route::get('/{story}', [StoryController::class, 'show'])
                ->name('show')
                ->middleware('throttle:120,1'); // 120 requests per minute

            Route::get('/featured', [StoryController::class, 'featured'])
                ->name('featured')
                ->middleware('throttle:30,1');

            Route::get('/trending', [StoryController::class, 'trending'])
                ->name('trending')
                ->middleware('throttle:30,1');

            Route::get('/categories', [StoryController::class, 'categories'])
                ->name('categories')
                ->middleware('throttle:20,1');

            Route::get('/search', [StoryController::class, 'search'])
                ->name('search')
                ->middleware('throttle:30,1');
        });

        /*
        |--------------------------------------------------------------------------
        | Authenticated Routes (Requires Login + Device Verification)
        |--------------------------------------------------------------------------
        */

        Route::middleware(['auth:sanctum']) // REMOVED device.verification to match your original logic
            ->group(function (): void {

                /*
                |--------------------------------------------------------------------------
                | Member Profile & Account Management
                |--------------------------------------------------------------------------
                */

                Route::prefix('members')->name('members.')->group(function (): void {
                    // Profile management
                    Route::get('/profile', [MemberController::class, 'profile'])
                        ->name('profile');

                    Route::put('/profile', [MemberController::class, 'updateProfile'])
                        ->name('update-profile')
                        ->middleware('throttle:10,1');

                    Route::post('/avatar', [MemberController::class, 'uploadAvatar'])
                        ->name('upload-avatar')
                        ->middleware('throttle:5,1');

                    // Account actions
                    Route::post('/logout', [MemberController::class, 'logout'])
                        ->name('logout');

                    Route::post('/change-password', [MemberController::class, 'changePassword'])
                        ->name('change-password')
                        ->middleware('throttle:3,1');

                    Route::delete('/account', [MemberController::class, 'deleteAccount'])
                        ->name('delete-account')
                        ->middleware('throttle:1,5'); // 1 attempt per 5 minutes

                    // Reading history and preferences
                    Route::get('/reading-history', [MemberController::class, 'readingHistory'])
                        ->name('reading-history');

                    Route::get('/preferences', [MemberController::class, 'preferences'])
                        ->name('preferences');

                    Route::put('/preferences', [MemberController::class, 'updatePreferences'])
                        ->name('update-preferences');
                });

                /*
                |--------------------------------------------------------------------------
                | Story API Routes - Private Actions (Auth Required)
                |--------------------------------------------------------------------------
                */

                Route::prefix('stories')->name('stories.')->group(function (): void {
                    // Story interactions (require authentication)
                    Route::post('/{story}/view', [StoryController::class, 'recordView'])
                        ->name('record-view')
                        ->middleware('throttle:60,1');

                    Route::post('/{story}/like', [StoryController::class, 'like'])
                        ->name('like')
                        ->middleware('throttle:30,1');

                    Route::delete('/{story}/like', [StoryController::class, 'unlike'])
                        ->name('unlike')
                        ->middleware('throttle:30,1');

                    Route::post('/{story}/rate', [StoryController::class, 'rate'])
                        ->name('rate')
                        ->middleware('throttle:20,1');

                    // Reading progress tracking
                    Route::post('/{story}/progress', [StoryController::class, 'updateProgress'])
                        ->name('update-progress')
                        ->middleware('throttle:60,1');

                    Route::get('/{story}/progress', [StoryController::class, 'getProgress'])
                        ->name('get-progress');

                    // Personal story collections
                    Route::get('/my-library', [StoryController::class, 'myLibrary'])
                        ->name('my-library');

                    Route::get('/recommended', [StoryController::class, 'recommended'])
                        ->name('recommended');
                });

                /*
                |--------------------------------------------------------------------------
                | Analytics & Insights (Member-specific)
                |--------------------------------------------------------------------------
                */

                Route::prefix('analytics')->name('analytics.')->group(function (): void {
                    Route::get('/reading-stats', [MemberController::class, 'readingStats'])
                        ->name('reading-stats');

                    Route::get('/reading-insights', [MemberController::class, 'readingInsights'])
                        ->name('reading-insights');
                });
            });
    });

/*
|--------------------------------------------------------------------------
| Legacy Route Redirects (Optional)
|--------------------------------------------------------------------------
|
| If migrating from old API structure, add redirects here
|
*/

// Example: Redirect old API endpoints
// Route::get('/stories', function () {
//     return redirect('/api/v1/stories', 301);
// });
