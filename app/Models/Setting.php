<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type', 'description'];

    protected $casts = [
        'value' => 'json',
    ];

    /**
     * Get a setting value by key with proper type casting
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            // Handle type casting based on the type field
            if ($setting->type) {
                return self::castValue($setting->value, $setting->type);
            }

            return $setting->value;
        });
    }

    /**
     * Cast value to proper type
     */
    private static function castValue($value, string $type): mixed
    {
        return match ($type) {
            'integer', 'number' => (int) $value,
            'float' => (float) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => is_array($value) ? $value : json_decode($value, true),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, mixed $value): Setting
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("setting_{$key}");
        Cache::forget('all_settings');
        Cache::forget("settings_group_{$setting->group}");

        return $setting;
    }

    /**
     * Get settings by group
     */
    public static function getGroup(string $group): array
    {
        return Cache::remember("settings_group_{$group}", 3600, function () use ($group) {
            return self::where('group', $group)
                ->get()
                ->mapWithKeys(function ($setting) {
                    $value = $setting->type ? self::castValue($setting->value, $setting->type) : $setting->value;
                    return [$setting->key => $value];
                })
                ->toArray();
        });
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        $keys = self::pluck('key');
        foreach ($keys as $key) {
            Cache::forget("setting_{$key}");
        }
        Cache::forget('all_settings');

        // Clear group caches
        $groups = self::distinct('group')->pluck('group');
        foreach ($groups as $group) {
            Cache::forget("settings_group_{$group}");
        }
    }
}
