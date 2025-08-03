<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    /**
     * Get story-related settings
     */
    public static function getStoryDefaults(): array
    {
        return Cache::remember('story_defaults', 3600, function () {
            return [
                'active_from_default' => Setting::get('story_active_from_default', 'now'),
                'active_until_hours' => Setting::get('story_active_until_hours', 24),
                'preview_length' => Setting::get('story_preview_length', 150),
                'auto_approve' => Setting::get('story_auto_approve', false),
                'require_featured_image' => Setting::get('story_require_featured_image', true),
            ];
        });
    }

    /**
     * Get the default active_from datetime based on settings
     */
    public static function getDefaultActiveFrom(): \Carbon\Carbon
    {
        $setting = Setting::get('story_active_from_default', 'now');
        return $setting === 'now' ? now() : today();
    }

    /**
     * Get the default active_until datetime based on settings
     */
    public static function getDefaultActiveUntil(): \Carbon\Carbon
    {
        $activeFrom = self::getDefaultActiveFrom();
        $hours = Setting::get('story_active_until_hours', 24);
        return $activeFrom->copy()->addHours($hours);
    }
}
