<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PublishingController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StoryManagementController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Filament Admin API Routes
|--------------------------------------------------------------------------
|
| These routes are automatically prefixed with 'admin/api' and assigned
| authentication middleware in bootstrap/app.php. They serve the Filament
| admin panel with real-time data and management capabilities.
|
| Authentication: Laravel Sanctum + Admin verification
| Rate Limiting: 120 requests per minute (higher than public API)
|
*/

// ✅ Admin health check - FIXED: Proper authentication handling
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Admin API is healthy',
        'timestamp' => now()->toISOString(),
        'user' => Auth::user()?->name, // ✅ FIXED: Use Auth::user() instead of auth()->user()
        'authenticated' => Auth::check(),
        'guard' => Auth::getDefaultDriver(),
    ]);
})->name('health');

/*
|--------------------------------------------------------------------------
| Dashboard & Overview Analytics
|--------------------------------------------------------------------------
*/

Route::prefix('dashboard')->name('dashboard.')->group(function (): void {
    // Real-time dashboard metrics
    Route::get('/overview', [DashboardController::class, 'getOverview'])
        ->name('overview');

    Route::get('/stats/today', [DashboardController::class, 'getTodayStats'])
        ->name('today-stats');

    // Chart data for dashboard widgets
    Route::get('/charts/views', [DashboardController::class, 'getViewsChart'])
        ->name('views-chart');

    Route::get('/charts/publishing', [DashboardController::class, 'getPublishingChart'])
        ->name('publishing-chart');

    Route::get('/charts/engagement', [DashboardController::class, 'getEngagementChart'])
        ->name('engagement-chart');

    // Dashboard actions
    Route::post('/refresh-cache', [DashboardController::class, 'refreshCache'])
        ->name('refresh-cache')
        ->middleware('throttle:5,1');
});

/*
|--------------------------------------------------------------------------
| Story Analytics & Performance
|--------------------------------------------------------------------------
*/

Route::prefix('analytics')->name('analytics.')->group(function (): void {
    // Story performance metrics
    Route::get('/stories/performance', [AnalyticsController::class, 'getStoryPerformance'])
        ->name('stories.performance');

    Route::get('/stories/trends', [AnalyticsController::class, 'getStoryTrends'])
        ->name('stories.trends');

    Route::get('/stories/{story}/detailed', [AnalyticsController::class, 'getDetailedStoryAnalytics'])
        ->name('stories.detailed');

    // Content analytics
    Route::get('/content/overview', [AnalyticsController::class, 'getContentOverview'])
        ->name('content.overview');

    Route::get('/content/categories', [AnalyticsController::class, 'getCategoryAnalytics'])
        ->name('content.categories');

    Route::get('/content/tags', [AnalyticsController::class, 'getTagAnalytics'])
        ->name('content.tags');

    // Real-time analytics
    Route::get('/realtime/views', [AnalyticsController::class, 'getRealTimeViews'])
        ->name('realtime.views');

    Route::get('/realtime/interactions', [AnalyticsController::class, 'getRealTimeInteractions'])
        ->name('realtime.interactions');

    // Export and reporting
    Route::post('/export/report', [AnalyticsController::class, 'exportReport'])
        ->name('export.report')
        ->middleware('throttle:3,1');

    Route::get('/reports/scheduled', [AnalyticsController::class, 'getScheduledReports'])
        ->name('reports.scheduled');
});

/*
|--------------------------------------------------------------------------
| Story Management & Content Operations
|--------------------------------------------------------------------------
*/

Route::prefix('stories')->name('stories.')->group(function (): void {
    // Advanced story management
    Route::get('/management-overview', [StoryManagementController::class, 'getOverview'])
        ->name('management-overview');

    Route::post('/bulk-actions', [StoryManagementController::class, 'processBulkActions'])
        ->name('bulk-actions')
        ->middleware('throttle:10,1');

    // Story scheduling and automation
    Route::get('/scheduling', [StoryManagementController::class, 'getSchedulingData'])
        ->name('scheduling');

    Route::post('/schedule-bulk', [StoryManagementController::class, 'scheduleBulkStories'])
        ->name('schedule-bulk')
        ->middleware('throttle:5,1');

    // Content optimization
    Route::post('/{story}/optimize', [StoryManagementController::class, 'optimizeStory'])
        ->name('optimize')
        ->middleware('throttle:20,1');

    Route::get('/{story}/preview-impact', [StoryManagementController::class, 'getPreviewImpact'])
        ->name('preview-impact');

    // Advanced search and filtering
    Route::post('/advanced-search', [StoryManagementController::class, 'advancedSearch'])
        ->name('advanced-search');

    Route::get('/filters/options', [StoryManagementController::class, 'getFilterOptions'])
        ->name('filters.options');

    // Story workflow management
    Route::post('/{story}/workflow/approve', [StoryManagementController::class, 'approveStory'])
        ->name('workflow.approve')
        ->middleware('throttle:30,1');

    Route::post('/{story}/workflow/reject', [StoryManagementController::class, 'rejectStory'])
        ->name('workflow.reject')
        ->middleware('throttle:30,1');
});

/*
|--------------------------------------------------------------------------
| Publishing & Content Operations
|--------------------------------------------------------------------------
*/

Route::prefix('publishing')->name('publishing.')->group(function (): void {
    // Publishing statistics and metrics
    Route::get('/stats', [PublishingController::class, 'getPublishingStats'])
        ->name('stats');

    Route::get('/chart-data', [PublishingController::class, 'getChartData'])
        ->name('chart-data');

    // Publishing workflow management
    Route::post('/queue/process', [PublishingController::class, 'processPublishingQueue'])
        ->name('queue.process')
        ->middleware('throttle:5,1');

    Route::get('/queue/status', [PublishingController::class, 'getQueueStatus'])
        ->name('queue.status');

    // Content scheduling optimization
    Route::post('/optimize-schedule', [PublishingController::class, 'optimizeSchedule'])
        ->name('optimize-schedule')
        ->middleware('throttle:3,1');

    Route::get('/schedule-recommendations', [PublishingController::class, 'getScheduleRecommendations'])
        ->name('schedule-recommendations');

    // Publishing history and tracking
    Route::get('/history', [PublishingController::class, 'getPublishingHistory'])
        ->name('history');

    Route::get('/impact-analysis', [PublishingController::class, 'getImpactAnalysis'])
        ->name('impact-analysis');

    // Automated publishing actions
    Route::post('/auto-publish/enable', [PublishingController::class, 'enableAutoPublish'])
        ->name('auto-publish.enable')
        ->middleware('throttle:5,1');

    Route::post('/auto-publish/configure', [PublishingController::class, 'configureAutoPublish'])
        ->name('auto-publish.configure')
        ->middleware('throttle:3,1');
});

/*
|--------------------------------------------------------------------------
| Member Management & User Analytics
|--------------------------------------------------------------------------
*/

Route::prefix('members')->name('members.')->group(function (): void {
    // Member overview and statistics
    Route::get('/overview', [AnalyticsController::class, 'getMemberOverview'])
        ->name('overview');

    Route::get('/engagement-metrics', [AnalyticsController::class, 'getMemberEngagement'])
        ->name('engagement-metrics');

    // Member segmentation and analysis
    Route::post('/segment-analysis', [AnalyticsController::class, 'getMemberSegmentation'])
        ->name('segment-analysis');

    Route::get('/retention-analysis', [AnalyticsController::class, 'getRetentionAnalysis'])
        ->name('retention-analysis');

    // Member management actions
    Route::post('/{member}/suspend', [AnalyticsController::class, 'suspendMember'])
        ->name('suspend')
        ->middleware('throttle:10,1');

    Route::post('/{member}/activate', [AnalyticsController::class, 'activateMember'])
        ->name('activate')
        ->middleware('throttle:10,1');
});


/*
|--------------------------------------------------------------------------
| API Documentation & Development Tools
|--------------------------------------------------------------------------
*/

Route::prefix('dev')->name('dev.')
    ->middleware('can:access-dev-tools') // Custom authorization
    ->group(function (): void {

        // API documentation endpoints
        Route::get('/routes', function () {
            return response()->json([
                'success' => true,
                'data' => \Illuminate\Support\Facades\Route::getRoutes()->get(),
            ]);
        })->name('routes');
    });
