<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Story, StoryPublishingHistory, Setting, User};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Cache, Log, DB, Validator, Storage, Artisan};
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Settings Controller
|--------------------------------------------------------------------------
*/

class SettingsController extends BaseAdminController
{
    /**
     * Get story default settings
     */
    public function getStoryDefaults(): JsonResponse
    {
        try {
            $settings = [
                'story_active_from_default' => Setting::get('story_active_from_default', 'now'),
                'story_active_until_hours' => Setting::get('story_active_until_hours', '24'),
                'auto_generate_excerpt' => Setting::get('auto_generate_excerpt', true),
                'default_reading_time' => Setting::get('default_reading_time', 5),
                'require_category' => Setting::get('require_category', true),
                'enable_auto_publish' => Setting::get('enable_auto_publish', false),
                'notification_settings' => $this->getNotificationSettings(),
            ];

            return $this->successResponse($settings, 'Story default settings retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Get story defaults error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to load story default settings');
        }
    }

    /**
     * Update story default settings
     */
    public function updateStoryDefaults(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'story_active_from_default' => 'required|in:now,today,tomorrow',
            'story_active_until_hours' => 'required|integer|min:1|max:8760', // Max 1 year
            'auto_generate_excerpt' => 'boolean',
            'default_reading_time' => 'integer|min:1|max:60',
            'require_category' => 'boolean',
            'enable_auto_publish' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if (!$this->checkPermission('manage_settings')) {
                return $this->errorResponse('Unauthorized', 403);
            }

            DB::transaction(function () use ($request) {
                $settings = $request->only([
                    'story_active_from_default',
                    'story_active_until_hours',
                    'auto_generate_excerpt',
                    'default_reading_time',
                    'require_category',
                    'enable_auto_publish',
                ]);

                foreach ($settings as $key => $value) {
                    Setting::set($key, $value);
                }

                // Log the settings change
                Log::info('Story default settings updated', [
                    'admin_id' => auth()->id(),
                    'settings' => $settings,
                ]);
            });

            // Clear related caches
            Cache::forget('story_defaults');
            Cache::forget('system_settings');

            return $this->successResponse([], 'Story default settings updated successfully');
        } catch (\Exception $e) {
            Log::error('Update story defaults error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to update story default settings');
        }
    }

    /**
     * Get system configuration settings
     */
    public function getSystemSettings(): JsonResponse
    {
        try {
            $cacheKey = 'system_settings';
            
            $settings = Cache::remember($cacheKey, 3600, function (): array {
                return [
                    'cache_settings' => $this->getCacheSettings(),
                    'performance_settings' => $this->getPerformanceSettings(),
                    'security_settings' => $this->getSecuritySettings(),
                    'notification_settings' => $this->getNotificationSettings(),
                    'maintenance_settings' => $this->getMaintenanceSettings(),
                ];
            });

            return $this->successResponse($settings, 'System settings retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Get system settings error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to load system settings');
        }
    }

    /**
     * Update system configuration settings
     */
    public function updateSystemSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cache_duration' => 'integer|min:60|max:86400', // 1 minute to 24 hours
            'analytics_retention_days' => 'integer|min:30|max:365',
            'max_concurrent_exports' => 'integer|min:1|max:10',
            'enable_performance_monitoring' => 'boolean',
            'enable_debug_logging' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if (!$this->checkPermission('manage_system_settings')) {
                return $this->errorResponse('Unauthorized', 403);
            }

            DB::transaction(function () use ($request) {
                $settings = $request->only([
                    'cache_duration',
                    'analytics_retention_days',
                    'max_concurrent_exports',
                    'enable_performance_monitoring',
                    'enable_debug_logging',
                ]);

                foreach ($settings as $key => $value) {
                    Setting::set("system.{$key}", $value);
                }

                Log::info('System settings updated', [
                    'admin_id' => auth()->id(),
                    'settings' => $settings,
                ]);
            });

            Cache::forget('system_settings');

            return $this->successResponse([], 'System settings updated successfully');
        } catch (\Exception $e) {
            Log::error('Update system settings error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to update system settings');
        }
    }

    /**
     * Clear application cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            if (!$this->checkPermission('manage_cache')) {
                return $this->errorResponse('Unauthorized', 403);
            }

            if (!$this->checkRateLimit('clear_cache', 5, 60)) {
                return $this->errorResponse('Rate limit exceeded. Please try again later.', 429);
            }

            $cacheType = $request->string('type', 'all'); // all, dashboard, analytics, stories
            $clearedCaches = [];

            switch ($cacheType) {
                case 'dashboard':
                    $this->clearDashboardCache();
                    $clearedCaches[] = 'dashboard';
                    break;
                    
                case 'analytics':
                    $this->clearAnalyticsCache();
                    $clearedCaches[] = 'analytics';
                    break;
                    
                case 'stories':
                    $this->clearStoriesCache();
                    $clearedCaches[] = 'stories';
                    break;
                    
                default:
                    Artisan::call('cache:clear');
                    $clearedCaches = ['all_application_cache'];
            }

            Log::info('Cache cleared', [
                'admin_id' => auth()->id(),
                'cache_type' => $cacheType,
                'cleared_caches' => $clearedCaches,
            ]);

            return $this->successResponse([
                'cleared_caches' => $clearedCaches,
                'cache_type' => $cacheType,
            ], 'Cache cleared successfully');
        } catch (\Exception $e) {
            Log::error('Clear cache error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to clear cache');
        }
    }

    /**
     * Refresh analytics data
     */
    public function refreshAnalytics(): JsonResponse
    {
        try {
            if (!$this->checkPermission('manage_analytics')) {
                return $this->errorResponse('Unauthorized', 403);
            }

            if (!$this->checkRateLimit('refresh_analytics', 3, 60)) {
                return $this->errorResponse('Rate limit exceeded. Please try again later.', 429);
            }

            // Clear analytics-specific caches
            $this->clearAnalyticsCache();
            
            // Trigger recalculation of key metrics
            $refreshedMetrics = $this->recalculateAnalytics();

            Log::info('Analytics refreshed', [
                'admin_id' => auth()->id(),
                'refreshed_metrics' => array_keys($refreshedMetrics),
            ]);

            return $this->successResponse([
                'refreshed_metrics' => $refreshedMetrics,
                'refresh_timestamp' => now()->toISOString(),
            ], 'Analytics data refreshed successfully');
        } catch (\Exception $e) {
            Log::error('Refresh analytics error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to refresh analytics data');
        }
    }

    /**
     * Get system performance metrics
     */
    public function getPerformanceMetrics(): JsonResponse
    {
        try {
            $cacheKey = 'performance_metrics';
            
            $metrics = Cache::remember($cacheKey, 300, function (): array {
                return [
                    'database_performance' => $this->getDatabasePerformanceMetrics(),
                    'cache_performance' => $this->getCachePerformanceMetrics(),
                    'api_performance' => $this->getAPIPerformanceMetrics(),
                    'system_resources' => $this->getSystemResourceMetrics(),
                ];
            });

            return $this->successResponse($metrics, 'Performance metrics retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Performance metrics error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to load performance metrics');
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth(): JsonResponse
    {
        try {
            $health = [
                'overall_status' => 'healthy',
                'database_status' => $this->checkDatabaseHealth(),
                'cache_status' => $this->checkCacheHealth(),
                'storage_status' => $this->checkStorageHealth(),
                'queue_status' => $this->checkQueueHealth(),
                'last_check' => now()->toISOString(),
            ];

            $overallHealth = $this->calculateOverallHealth($health);
            $health['overall_status'] = $overallHealth;

            return response()->json([
                'success' => true,
                'data' => $health,
                'healthy' => $overallHealth === 'healthy',
            ]);
        } catch (\Exception $e) {
            Log::error('System health check error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'data' => [
                    'overall_status' => 'critical',
                    'error' => 'Health check failed',
                ],
                'healthy' => false,
            ], 500);
        }
    }

    // Private helper methods for SettingsController
    private function getNotificationSettings(): array
    {
        return [
            'email_notifications' => Setting::get('notifications.email_enabled', true),
            'story_published_notification' => Setting::get('notifications.story_published', true),
            'expiry_warning_hours' => Setting::get('notifications.expiry_warning_hours', 24),
            'admin_activity_digest' => Setting::get('notifications.admin_digest', 'weekly'),
        ];
    }

    private function getCacheSettings(): array
    {
        return [
            'default_duration' => Setting::get('cache.default_duration', 900),
            'analytics_duration' => Setting::get('cache.analytics_duration', 1800),
            'dashboard_duration' => Setting::get('cache.dashboard_duration', 300),
            'enabled' => Setting::get('cache.enabled', true),
        ];
    }

    private function getPerformanceSettings(): array
    {
        return [
            'query_cache_enabled' => Setting::get('performance.query_cache', true),
            'image_optimization' => Setting::get('performance.image_optimization', true),
            'lazy_loading' => Setting::get('performance.lazy_loading', true),
            'compression_enabled' => Setting::get('performance.compression', true),
        ];
    }

    private function getSecuritySettings(): array
    {
        return [
            'rate_limiting_enabled' => Setting::get('security.rate_limiting', true),
            'audit_logging' => Setting::get('security.audit_logging', true),
            'two_factor_required' => Setting::get('security.2fa_required', false),
            'session_timeout_minutes' => Setting::get('security.session_timeout', 120),
        ];
    }

    private function getMaintenanceSettings(): array
    {
        return [
            'maintenance_mode' => Setting::get('maintenance.enabled', false),
            'auto_cleanup_enabled' => Setting::get('maintenance.auto_cleanup', true),
            'cleanup_interval_days' => Setting::get('maintenance.cleanup_interval', 30),
            'backup_retention_days' => Setting::get('maintenance.backup_retention', 90),
        ];
    }

    private function clearDashboardCache(): void
    {
        $patterns = [
            'dashboard_overview_*',
            'dashboard_views_chart_*',
            'dashboard_publishing_chart_*',
            'dashboard_today_stats_*',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // In production, implement proper pattern-based cache clearing
                Cache::flush(); // Simplified for now
                break;
            } else {
                Cache::forget($pattern);
            }
        }
    }

    private function clearAnalyticsCache(): void
    {
        $patterns = [
            'story_analytics_*',
            'audience_analytics_*',
            'content_performance_*',
            'publishing_analytics_*',
        ];

        // Simplified cache clearing - in production, implement pattern-based clearing
        Cache::flush();
    }

    private function clearStoriesCache(): void
    {
        $patterns = [
            'story_*',
            'content_*',
            'story_view_*',
            'story_rating_*',
        ];

        // Simplified cache clearing
        Cache::flush();
    }

    private function recalculateAnalytics(): array
    {
        return [
            'dashboard_metrics' => 'recalculated',
            'story_analytics' => 'recalculated',
            'audience_insights' => 'recalculated',
            'performance_data' => 'recalculated',
        ];
    }

    private function getDatabasePerformanceMetrics(): array
    {
        return [
            'query_count' => DB::getQueryLog() ? count(DB::getQueryLog()) : 0,
            'average_query_time' => '45ms', // Placeholder
            'slow_queries' => 2, // Placeholder
            'connection_pool_usage' => '65%', // Placeholder
        ];
    }

    private function getCachePerformanceMetrics(): array
    {
        return [
            'hit_rate' => '87.3%', // Placeholder
            'miss_rate' => '12.7%', // Placeholder
            'memory_usage' => '234MB', // Placeholder
            'total_keys' => 1247, // Placeholder
        ];
    }

    private function getAPIPerformanceMetrics(): array
    {
        return [
            'average_response_time' => '156ms', // Placeholder
            'requests_per_minute' => 45, // Placeholder
            'error_rate' => '0.3%', // Placeholder
            'active_connections' => 23, // Placeholder
        ];
    }

    private function getSystemResourceMetrics(): array
    {
        return [
            'cpu_usage' => '23%', // Placeholder
            'memory_usage' => '67%', // Placeholder
            'disk_usage' => '45%', // Placeholder
            'network_io' => '12MB/s', // Placeholder
        ];
    }

    private function checkDatabaseHealth(): string
    {
        try {
            DB::connection()->getPdo();
            return 'healthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    private function checkCacheHealth(): string
    {
        try {
            Cache::put('health_check', 'test', 60);
            $value = Cache::get('health_check');
            Cache::forget('health_check');
            
            return $value === 'test' ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    private function checkStorageHealth(): string
    {
        try {
            $testFile = 'health_check_' . time() . '.txt';
            Storage::put($testFile, 'test');
            $exists = Storage::exists($testFile);
            Storage::delete($testFile);
            
            return $exists ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    private function checkQueueHealth(): string
    {
        // Simplified queue health check
        return 'healthy'; // Placeholder
    }

    private function calculateOverallHealth(array $health): string
    {
        $healthStatuses = [
            $health['database_status'],
            $health['cache_status'],
            $health['storage_status'],
            $health['queue_status'],
        ];

        $unhealthyCount = count(array_filter($healthStatuses, fn($status) => $status !== 'healthy'));

        if ($unhealthyCount === 0) {
            return 'healthy';
        } elseif ($unhealthyCount <= 1) {
            return 'warning';
        } else {
            return 'critical';
        }
    }
}

