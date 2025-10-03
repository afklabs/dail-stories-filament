<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class FCMToken extends Model
{
    protected $table = 'fcm_tokens';

    protected $fillable = [
        'member_id',
        'fcm_token',
        'device_id',
        'platform',
        'device_info',
        'app_version',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['fcm_token'];

    // Relationship
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeForMember($query, int $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    // Helper Methods
    public function markAsUsed(): bool
    {
        return $this->update(['last_used_at' => now()]);
    }

    public static function cleanupOldTokens(int $days = 90): int
    {
        return static::where('last_used_at', '<', Carbon::now()->subDays($days))
            ->orWhere('is_active', false)
            ->delete();
    }
}
