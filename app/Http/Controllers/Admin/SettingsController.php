<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Get story default settings
     */
    public function getStoryDefaults(): JsonResponse
    {
        try {
            $settings = Cache::remember('story_defaults', 3600, function () {
                return [
                    'active_from_default' => Setting::get('story_active_from_default', 'now'),
                    'active_until_hours' => Setting::get('story_active_until_hours', 24),
                    'preview_length' => Setting::get('story_preview_length', 150),
                    'auto_approve' => Setting::get('story_auto_approve', false),
                    'require_featured_image' => Setting::get('story_require_featured_image', true),
                ];
            });

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
            'active_from_default' => 'required|in:now,today',
            'active_until_hours' => 'required|integer|min:1|max:8760',
            'preview_length' => 'integer|min:50|max:500',
            'auto_approve' => 'boolean',
            'require_featured_image' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                $settings = $request->only([
                    'active_from_default',
                    'active_until_hours',
                    'preview_length',
                    'auto_approve',
                    'require_featured_image',
                ]);

                foreach ($settings as $key => $value) {
                    Setting::set("story_{$key}", $value);
                }

                Log::info('Story default settings updated', [
                    'admin_id' => auth()->id(),
                    'settings' => $settings,
                ]);
            });

            Cache::forget('story_defaults');

            return $this->successResponse([], 'Story default settings updated successfully');
        } catch (\Exception $e) {
            Log::error('Update story defaults error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to update story default settings');
        }
    }

    /**
     * Get all system settings organized by group
     */
    public function getAllSettings(): JsonResponse
    {
        try {
            $settings = Cache::remember('all_settings', 3600, function () {
                return Setting::all()
                    ->groupBy('group')
                    ->map(function ($group) {
                        return $group->pluck('value', 'key');
                    });
            });

            return $this->successResponse($settings, 'All settings retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Get all settings error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to load settings');
        }
    }

    /**
     * Clear application cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            // Clear Laravel cache
            Cache::flush();
            
            // Clear settings cache
            Setting::clearCache();
            
            // Clear config cache
            \Artisan::call('config:clear');
            \Artisan::call('route:clear');
            \Artisan::call('view:clear');

            Log::info('Cache cleared', [
                'admin_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([], 'Cache cleared successfully');
        } catch (\Exception $e) {
            Log::error('Clear cache error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to clear cache');
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): JsonResponse
    {
        try {
            $metrics = [
                'cache_hit_rate' => Cache::get('metrics.cache_hit_rate', 0),
                'average_response_time' => Cache::get('metrics.avg_response_time', 0),
                'database_query_count' => Cache::get('metrics.db_query_count', 0),
                'memory_usage' => memory_get_usage(true) / 1024 / 1024, // MB
                'peak_memory_usage' => memory_get_peak_usage(true) / 1024 / 1024, // MB
                'uptime' => Cache::get('app.start_time') 
                    ? now()->diffInMinutes(Cache::get('app.start_time')) 
                    : 0,
            ];

            return $this->successResponse($metrics, 'Performance metrics retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Get performance metrics error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to load performance metrics');
        }
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

    private function getNotificationSettings(): array
    {
        return [
            'new_member_notification' => Setting::get('notifications.new_member', true),
            'story_approval_notification' => Setting::get('notifications.story_approval', true),
            'low_engagement_alert' => Setting::get('notifications.low_engagement', true),
            'expiry_warning_enabled' => Setting::get('notifications.expiry_warning', true),
            'expiry_warning_hours' => Setting::get('notifications.expiry_warning_hours', 24),
            'admin_activity_digest' => Setting::get('notifications.admin_digest', 'weekly'),
        ];
    }

    private function getMaintenanceSettings(): array
    {
        return [
            'maintenance_mode' => Setting::get('maintenance.enabled', false),
            'auto_cleanup_enabled' => Setting::get('maintenance.auto_cleanup', true),
            'cleanup_interval_days' => Setting::get('maintenance.cleanup_interval', 30),
            'backup_enabled' => Setting::get('maintenance.backup_enabled', true),
            'backup_retention_days' => Setting::get('maintenance.backup_retention', 30),
        ];
    }
}
