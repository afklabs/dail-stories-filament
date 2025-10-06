<?php

declare(strict_types=1);

use App\Http\Controllers\API\MemberController;
use App\Http\Controllers\API\StoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\FCMController;
use App\Http\Controllers\API\FAQController;


/*
|--------------------------------------------------------------------------
| Public API Routes for Flutter App
|--------------------------------------------------------------------------
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

// 🔧 DEBUG: Simple test route - NO MIDDLEWARE
Route::get('/test', function () {
    return response()->json([
        'status' => 'API is working',
        'time' => now()->toISOString(),
        'message' => 'If you see this, Laravel is accessible'
    ]);
});

// ✅ API Authentication route
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json([
        'success' => true,
        'data' => $request->user(),
    ]);
})->name('user');

/*
|--------------------------------------------------------------------------
| API v1 Routes - TEMPORARILY REMOVED api.version MIDDLEWARE
|--------------------------------------------------------------------------
*/

Route::prefix('v1')
    ->name('api.v1.')
    // ->middleware(['api.version']) // 🔧 TEMPORARILY COMMENTED OUT
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
                ->middleware('throttle:5,1');

            Route::post('/login', [MemberController::class, 'login'])
                ->name('login')
                ->middleware('throttle:10,1');

            Route::post('/forgot-password', [MemberController::class, 'forgotPassword'])
                ->name('forgot-password')
                ->middleware('throttle:3,1');
        });

        // Get ALL categories (for submission form)
        Route::get('/categories/all', function () {
            $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);
            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        })->name('categories.all');


        // Public story browsing - NO DEVICE VERIFICATION FOR PUBLIC ROUTES
        Route::prefix('stories')->name('stories.')->group(function (): void {
            // ✅ PRIMARY DISCOVERY ROUTES - Public Access
            Route::get('/', [StoryController::class, 'index'])
                ->name('index')
                ->middleware('throttle:60,1');

            Route::get('/{story}', [StoryController::class, 'show'])
                ->name('show')
                ->middleware('throttle:120,1');

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
        // Story interactions - Protected routes (require authentication)
        Route::prefix('stories')->name('stories.')
            ->middleware('auth:sanctum')
            ->group(function (): void {
                Route::get('/{story}/progress', [StoryController::class, 'getReadingProgress'])
                    ->name('progress.get')
                    ->middleware('throttle:60,1');

                Route::post('/{story}/progress', [StoryController::class, 'updateReadingProgress'])
                    ->name('progress.update')
                    ->middleware('throttle:60,1');

                Route::get('/{story}/rating', [StoryController::class, 'getUserRating'])
                    ->name('rating.get')
                    ->middleware('throttle:60,1');
            });

        // Categories and Tags endpoints
        Route::get('/categories', [StoryController::class, 'getCategories'])
            ->name('categories');

        Route::get('/tags', [StoryController::class, 'getTags'])
            ->name('tags');

        /*
        |--------------------------------------------------------------------------
        | Authenticated Routes (Requires Login)
        |--------------------------------------------------------------------------
        */

        Route::middleware(['auth:sanctum'])
            ->group(function (): void {

                /*
                |--------------------------------------------------------------------------
                | Member Profile & Account Management
                |--------------------------------------------------------------------------
                */

                Route::prefix('members')->name('members.')
                    ->group(function (): void {
                        Route::get('/profile', [MemberController::class, 'profile'])
                            ->name('profile');

                        Route::put('/profile', [MemberController::class, 'updateProfile'])
                            ->name('update-profile')
                            ->middleware('throttle:10,1');

                        Route::post('/logout', [MemberController::class, 'logout'])
                            ->name('logout');

                        Route::post('/change-password', [MemberController::class, 'changePassword'])
                            ->name('change-password')
                            ->middleware('throttle:3,1');

                        Route::post('/avatar', [MemberController::class, 'uploadAvatar'])
                            ->name('upload-avatar')
                            ->middleware('throttle:5,1');

                        Route::delete('/account', [MemberController::class, 'deleteAccount'])
                            ->name('delete-account')
                            ->middleware('throttle:1,5');

                        // ✅ NEW: Reading achievements endpoint for profile cards
                        Route::get('/reading-achievements', [MemberController::class, 'getReadingAchievements'])
                            ->name('reading-achievements')
                            ->middleware('throttle:30,1');
                    });

                /*
                |--------------------------------------------------------------------------
                | Member Story Interactions
                |--------------------------------------------------------------------------
                */

                Route::prefix('members/stories/{story}')->name('members.stories.')
                    ->group(function (): void {
                        Route::post('/interact', [StoryController::class, 'interact'])
                            ->name('interact')
                            ->middleware('throttle:30,1');

                        Route::post('/progress', [StoryController::class, 'updateReadingProgress'])
                            ->name('progress')
                            ->middleware('throttle:60,1');

                        Route::post('/rate', [StoryController::class, 'rate'])
                            ->name('rate')
                            ->middleware('throttle:20,1');
                    });

                /*
                |--------------------------------------------------------------------------
                | Member Collections & History
                |--------------------------------------------------------------------------
                */

                Route::prefix('members')->name('members.')
                    ->group(function (): void {
                        Route::get('/bookmarks', [MemberController::class, 'getBookmarks'])
                            ->name('bookmarks');

                        Route::get('/rated-stories', [MemberController::class, 'getRatedStories'])
                            ->name('rated-stories');

                        Route::get('/reading-history', [MemberController::class, 'getReadingHistory'])
                            ->name('reading-history');

                        Route::get('/recommendations', [MemberController::class, 'getRecommendations'])
                            ->name('recommendations');

                        Route::get('/stats', [MemberController::class, 'getStats'])
                            ->name('stats');

                        Route::get('/achievements', [MemberController::class, 'getAchievements'])
                            ->name('achievements');

                        Route::get('/reading-streak', [MemberController::class, 'getReadingStreak'])
                            ->name('reading-streak');

                        Route::get('/preferences', [MemberController::class, 'getPreferences'])
                            ->name('preferences');

                        Route::put('/preferences', [MemberController::class, 'updatePreferences'])
                            ->name('update-preferences')
                            ->middleware('throttle:10,1');
                    });
            });

        /*
        |--------------------------------------------------------------------------
        | Public Story Engagement Routes (Device ID for Analytics Only)
        |--------------------------------------------------------------------------
        */

        Route::prefix('stories/{story}')->name('stories.')
            ->group(function (): void {
                // These work without auth but track device ID for analytics
                Route::post('/view', [StoryController::class, 'recordView'])
                    ->name('view')
                    ->middleware('throttle:60,1');

                Route::post('/rating', [StoryController::class, 'submitRating'])
                    ->name('rating')
                    ->middleware('throttle:20,1');
            });

        // Public FCM routes (guest + members)
        Route::post('/fcm/token', [FCMController::class, 'storeToken']);
        Route::delete('/fcm/token', [FCMController::class, 'deleteToken']);

        // Protected routes (members only)
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/members/fcm-tokens', [FCMController::class, 'getTokens']);
        });

        /*
        |--------------------------------------------------------------------------
        | Story Submissions (Member Feature)
        |--------------------------------------------------------------------------
        */

        // Public endpoint - Get submission settings
        Route::prefix('submissions')->name('submissions.')->group(function (): void {
            Route::get('/settings', [App\Http\Controllers\API\MemberStorySubmissionController::class, 'getSettings'])
                ->name('settings')
                ->middleware('throttle:20,1');
        });

        // Protected endpoints - Submit stories
        Route::prefix('submissions')
            ->name('submissions.')
            ->middleware(['auth:sanctum'])
            ->group(function (): void {
                Route::post('/submit', [App\Http\Controllers\API\MemberStorySubmissionController::class, 'submit'])
                    ->name('submit')
                    ->middleware('throttle:5,1');

                // Future feature - member submission history
                Route::get('/my-submissions', [App\Http\Controllers\API\MemberStorySubmissionController::class, 'mySubmissions'])
                    ->name('my-submissions')
                    ->middleware('throttle:20,1');
            });


        /*
|--------------------------------------------------------------------------
| FAQ Routes (Public - No Authentication Required)
|--------------------------------------------------------------------------
*/

        Route::prefix('v1/faqs')->group(function () {
            // Get all active FAQs
            Route::get('/', [FAQController::class, 'index'])
                ->name('faqs.index');

            // Get specific FAQ by ID
            Route::get('/{id}', [FAQController::class, 'show'])
                ->name('faqs.show');
        });
    });
