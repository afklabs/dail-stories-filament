<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Submission Setting Model
 * 
 * @property int $id
 * @property string $setting_key
 * @property string $setting_value
 * @property string $setting_type
 * @property bool $is_active
 */
class SubmissionSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get the guide text for submissions
     */
    public static function getGuideText(): string
    {
        return Cache::remember('submission_guide_text', self::CACHE_TTL, function () {
            $setting = self::where('setting_key', 'submission_guide')
                ->where('is_active', true)
                ->first();

            return $setting?->setting_value ?? 'مرحباً بك في صفحة إرسال القصص!';
        });
    }

    /**
     * Get the terms text for submissions
     */
    public static function getTermsText(): string
    {
        return Cache::remember('submission_terms_text', self::CACHE_TTL, function () {
            $setting = self::where('setting_key', 'submission_terms')
                ->where('is_active', true)
                ->first();

            return $setting?->setting_value ?? 'يرجى الموافقة على الشروط والأحكام.';
        });
    }

    /**
     * Update the guide text
     */
    public static function updateGuideText(string $text): bool
    {
        $setting = self::updateOrCreate(
            ['setting_key' => 'submission_guide'],
            [
                'setting_value' => $text,
                'setting_type' => 'guide',
                'is_active' => true,
            ]
        );

        Cache::forget('submission_guide_text');

        return $setting->wasRecentlyCreated || $setting->wasChanged();
    }

    /**
     * Update the terms text
     */
    public static function updateTermsText(string $text): bool
    {
        $setting = self::updateOrCreate(
            ['setting_key' => 'submission_terms'],
            [
                'setting_value' => $text,
                'setting_type' => 'terms',
                'is_active' => true,
            ]
        );

        Cache::forget('submission_terms_text');

        return $setting->wasRecentlyCreated || $setting->wasChanged();
    }

    /**
     * Clear all submission settings cache
     */
    public static function clearCache(): void
    {
        Cache::forget('submission_guide_text');
        Cache::forget('submission_terms_text');
    }
}
