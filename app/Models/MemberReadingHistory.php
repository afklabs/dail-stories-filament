<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class MemberReadingHistory extends Model
{
    use HasFactory;

    protected $table = 'member_reading_history';

    // ✅ UPDATED: Include new fields in fillable
    protected $fillable = [
        'member_id',
        'story_id',
        'reading_progress',
        'time_spent',
        'last_read_at',
        'reading_sessions',  // New field
        'bookmarks',         // New field
        'metadata',          // New field
    ];

    // ✅ FIXED: Add proper casts for new JSON fields
    protected $casts = [
        'reading_progress' => 'decimal:2',
        'time_spent' => 'integer',
        'reading_sessions' => 'integer',  // New field
        'last_read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'member_id' => 'integer',
        'story_id' => 'integer',
        'bookmarks' => 'array',           // New JSON field
        'metadata' => 'array',            // New JSON field
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS - ✅ IMPROVED with better performance
    |--------------------------------------------------------------------------
    */

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES - ✅ IMPROVED with better naming and performance
    |--------------------------------------------------------------------------
    */

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('reading_progress', '>=', 100);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('reading_progress', '>', 0)
            ->where('reading_progress', '<', 100);
    }

    public function scopeStarted(Builder $query): Builder
    {
        return $query->where('reading_progress', '>', 0);
    }

    public function scopeNotStarted(Builder $query): Builder
    {
        return $query->where('reading_progress', '<=', 0);
    }

    public function scopeByMember(Builder $query, int $memberId): Builder
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeByStory(Builder $query, int $storyId): Builder
    {
        return $query->where('story_id', $storyId);
    }

    public function scopeRecentlyRead(Builder $query, int $days = 7): Builder
    {
        return $query->where('last_read_at', '>=', now()->subDays($days));
    }

    public function scopeByProgress(Builder $query, float $min = 0, float $max = 100): Builder
    {
        return $query->whereBetween('reading_progress', [$min, $max]);
    }

    // ✅ NEW: Additional useful scopes
    public function scopeHighProgress(Builder $query): Builder
    {
        return $query->where('reading_progress', '>=', 75);
    }

    public function scopeLongReads(Builder $query, int $minMinutes = 30): Builder
    {
        return $query->where('time_spent', '>=', $minMinutes * 60);
    }

    public function scopeMultipleSessions(Builder $query): Builder
    {
        return $query->where('reading_sessions', '>', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS - ✅ IMPROVED with Laravel 9+ syntax and better validation
    |--------------------------------------------------------------------------
    */

    protected function progressPercentage(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => number_format((float) $this->reading_progress, 1) . '%'
        );
    }

    protected function timeSpentMinutes(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => round($this->time_spent / 60, 1)
        );
    }

    protected function timeSpentHours(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => round($this->time_spent / 3600, 2)
        );
    }

    protected function isCompleted(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => $this->reading_progress >= 100
        );
    }

    protected function progressStatus(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function () {
                if ($this->reading_progress >= 100) return 'completed';
                if ($this->reading_progress > 50) return 'halfway';
                if ($this->reading_progress > 0) return 'started';
                return 'not_started';
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS - ✅ NEW: Enhanced functionality
    |--------------------------------------------------------------------------
    */

    /**
     * Get metadata value safely
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value safely
     */
    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
    }

    /**
     * Add bookmark position
     */
    public function addBookmark(int $position, string $note = null): void
    {
        $bookmarks = $this->bookmarks ?? [];
        $bookmarks[] = [
            'position' => $position,
            'note' => $note,
            'created_at' => now()->toISOString(),
        ];
        $this->bookmarks = $bookmarks;
    }

    /**
     * Remove bookmark by position
     */
    public function removeBookmark(int $position): void
    {
        if (!$this->bookmarks) return;

        $bookmarks = collect($this->bookmarks)
            ->filter(fn($bookmark) => $bookmark['position'] !== $position)
            ->values()
            ->toArray();

        $this->bookmarks = $bookmarks;
    }

    /**
     * Get all bookmark positions
     */
    public function getBookmarkPositions(): array
    {
        if (!$this->bookmarks) return [];

        return collect($this->bookmarks)
            ->pluck('position')
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC HELPER METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Create or update reading history
     */
    public static function recordProgress(
        int $memberId,
        int $storyId,
        float $progress,
        int $timeSpent = 0,
        array $metadata = []
    ): self {
        // Find existing record
        $history = self::where('member_id', $memberId)
            ->where('story_id', $storyId)
            ->first();

        if ($history) {
            // Update existing record
            $history->update([
                'reading_progress' => $progress,
                'time_spent' => $timeSpent,
                'last_read_at' => now(),
                'reading_sessions' => $history->reading_sessions + 1, // Safe increment
                'metadata' => array_merge($history->metadata ?? [], $metadata),
            ]);
        } else {
            // Create new record
            $history = self::create([
                'member_id' => $memberId,
                'story_id' => $storyId,
                'reading_progress' => $progress,
                'time_spent' => $timeSpent,
                'last_read_at' => now(),
                'reading_sessions' => 1, // Start with 1 for new records
                'metadata' => $metadata,
            ]);
        }

        return $history;
    }

    /**
     * Get reading statistics for a member
     */
    public static function getMemberStats(int $memberId): array
    {
        $stats = self::where('member_id', $memberId)
            ->selectRaw('
                COUNT(*) as total_stories,
                SUM(CASE WHEN reading_progress >= 100 THEN 1 ELSE 0 END) as completed_stories,
                SUM(CASE WHEN reading_progress > 0 AND reading_progress < 100 THEN 1 ELSE 0 END) as in_progress_stories,
                AVG(reading_progress) as avg_progress,
                SUM(time_spent) as total_time_spent,
                SUM(reading_sessions) as total_sessions
            ')
            ->first();

        return [
            'total_stories' => $stats->total_stories ?? 0,
            'completed_stories' => $stats->completed_stories ?? 0,
            'in_progress_stories' => $stats->in_progress_stories ?? 0,
            'completion_rate' => $stats->total_stories > 0
                ? round(($stats->completed_stories / $stats->total_stories) * 100, 1)
                : 0,
            'average_progress' => round($stats->avg_progress ?? 0, 1),
            'total_time_spent_minutes' => round(($stats->total_time_spent ?? 0) / 60, 1),
            'total_sessions' => $stats->total_sessions ?? 0,
        ];
    }
}
