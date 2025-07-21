<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class MemberReadingHistory extends Model
{
    use HasFactory;

    protected $table = 'member_reading_history';

    // ✅ IMPROVED: Better organized fillable with validation in mind
    protected $fillable = [
        'member_id',
        'story_id',
        'reading_progress',
        'time_spent',
        'last_read_at',
    ];

    // ✅ IMPROVED: Enhanced casting with proper types
    protected $casts = [
        'reading_progress' => 'decimal:2',
        'time_spent' => 'integer',
        'last_read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'member_id' => 'integer',
        'story_id' => 'integer',
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

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS - ✅ IMPROVED with Laravel 9+ syntax and better validation
    |--------------------------------------------------------------------------
    */

    protected function progressPercentage(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => number_format((float) $this->reading_progress, 1) . '%'
        );
    }

    protected function formattedTimeSpent(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function () {
                $totalSeconds = (int) $this->time_spent;
                $hours = intdiv($totalSeconds, 3600);
                $minutes = intdiv($totalSeconds % 3600, 60);
                $seconds = $totalSeconds % 60;

                if ($hours > 0) {
                    return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
                } elseif ($minutes > 0) {
                    return sprintf('%dm %ds', $minutes, $seconds);
                }
                
                return sprintf('%ds', $seconds);
            }
        );
    }

    protected function timeSpentInMinutes(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => round((float) $this->time_spent / 60, 2)
        );
    }

    protected function isCompleted(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->reading_progress >= 100
        );
    }

    protected function isStarted(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->reading_progress > 0
        );
    }

    protected function progressStatus(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function () {
                return match (true) {
                    $this->reading_progress <= 0 => 'not_started',
                    $this->reading_progress >= 100 => 'completed',
                    $this->reading_progress >= 75 => 'almost_done',
                    $this->reading_progress >= 25 => 'in_progress',
                    default => 'just_started'
                };
            }
        );
    }

    protected function progressColor(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => match ($this->progress_status) {
                'not_started' => 'gray',
                'just_started' => 'blue',
                'in_progress' => 'yellow',
                'almost_done' => 'orange',
                'completed' => 'green',
                default => 'gray'
            }
        );
    }

    protected function lastReadHuman(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->last_read_at?->diffForHumans() ?? 'Never'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | METHODS - ✅ IMPROVED with better error handling and validation
    |--------------------------------------------------------------------------
    */

    public function updateProgress(float $progress, int $additionalTime = 0): bool
    {
        try {
            // Validate input
            $progress = max(0, min(100, $progress));
            $additionalTime = max(0, $additionalTime);

            return $this->update([
                'reading_progress' => $progress,
                'time_spent' => $this->time_spent + $additionalTime,
                'last_read_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update reading progress', [
                'history_id' => $this->id,
                'progress' => $progress,
                'additional_time' => $additionalTime,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function markCompleted(int $additionalTime = 0): bool
    {
        return $this->updateProgress(100, $additionalTime);
    }

    public function addReadingTime(int $seconds): bool
    {
        try {
            return $this->update([
                'time_spent' => $this->time_spent + max(0, $seconds),
                'last_read_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to add reading time', [
                'history_id' => $this->id,
                'seconds' => $seconds,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC METHODS - ✅ IMPROVED with caching and performance optimization
    |--------------------------------------------------------------------------
    */

    public static function getMemberStats(int $memberId): array
    {
        return cache()->remember("member_reading_stats_{$memberId}", 300, function () use ($memberId) {
            $history = self::where('member_id', $memberId);

            $totalStories = $history->count();
            $completedStories = $history->clone()->completed()->count();

            return [
                'total_stories' => $totalStories,
                'completed_stories' => $completedStories,
                'in_progress_stories' => $history->clone()->inProgress()->count(),
                'not_started_stories' => $history->clone()->notStarted()->count(),
                'total_reading_time_minutes' => round($history->sum('time_spent') / 60, 2),
                'avg_progress' => round($history->avg('reading_progress'), 2),
                'avg_reading_time_minutes' => round($history->avg('time_spent') / 60, 2),
                'completion_rate' => $totalStories > 0 
                    ? round(($completedStories / $totalStories) * 100, 1) 
                    : 0,
                'last_read_at' => $history->latest('last_read_at')->value('last_read_at'),
            ];
        });
    }

    public static function getStoryStats(int $storyId): array
    {
        return cache()->remember("story_reading_stats_{$storyId}", 300, function () use ($storyId) {
            $history = self::where('story_id', $storyId);

            $totalReaders = $history->count();
            $completedReaders = $history->clone()->completed()->count();

            return [
                'total_readers' => $totalReaders,
                'completed_readers' => $completedReaders,
                'in_progress_readers' => $history->clone()->inProgress()->count(),
                'avg_progress' => round($history->avg('reading_progress'), 2),
                'avg_reading_time_minutes' => round($history->avg('time_spent') / 60, 2),
                'total_reading_time_minutes' => round($history->sum('time_spent') / 60, 2),
                'completion_rate' => $totalReaders > 0 
                    ? round(($completedReaders / $totalReaders) * 100, 1) 
                    : 0,
                'most_recent_read' => $history->latest('last_read_at')->value('last_read_at'),
            ];
        });
    }

    public static function getTopReaders(int $limit = 10): Collection
    {
        return cache()->remember("top_readers_{$limit}", 600, function () use ($limit) {
            return self::with(['member:id,name,email'])
                ->selectRaw('member_id, SUM(time_spent) as total_time, COUNT(*) as stories_count, AVG(reading_progress) as avg_progress')
                ->groupBy('member_id')
                ->having('total_time', '>', 0)
                ->orderByDesc('total_time')
                ->limit($limit)
                ->get();
        });
    }

    public static function getMostReadStories(int $limit = 10): Collection
    {
        return cache()->remember("most_read_stories_{$limit}", 600, function () use ($limit) {
            return self::with(['story:id,title,slug'])
                ->selectRaw('story_id, COUNT(*) as readers_count, AVG(reading_progress) as avg_progress, SUM(time_spent) as total_time')
                ->groupBy('story_id')
                ->having('readers_count', '>', 0)
                ->orderByDesc('readers_count')
                ->limit($limit)
                ->get();
        });
    }

    public static function getReadingTrends(int $days = 30): Collection
    {
        return cache()->remember("reading_trends_{$days}", 300, function () use ($days) {
            return self::selectRaw('DATE(last_read_at) as date, COUNT(DISTINCT member_id) as unique_readers, COUNT(*) as total_sessions, AVG(reading_progress) as avg_progress, SUM(time_spent) as total_time')
                ->where('last_read_at', '>=', now()->subDays($days))
                ->groupBy('date')
                ->orderByDesc('date')
                ->get()
                ->map(function ($item) {
                    $item->total_time_minutes = round($item->total_time / 60, 2);
                    $item->avg_progress = round($item->avg_progress, 2);
                    return $item;
                });
        });
    }

    // ✅ NEW: Additional analytics methods
    public static function getCompletionTrends(int $days = 30): array
    {
        return cache()->remember("completion_trends_{$days}", 300, function () use ($days) {
            $data = self::selectRaw('DATE(last_read_at) as date, COUNT(*) as total, SUM(CASE WHEN reading_progress >= 100 THEN 1 ELSE 0 END) as completed')
                ->where('last_read_at', '>=', now()->subDays($days))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return $data->map(function ($item) {
                $item->completion_rate = $item->total > 0 
                    ? round(($item->completed / $item->total) * 100, 2) 
                    : 0;
                return $item;
            })->toArray();
        });
    }

    public static function getAverageReadingSpeed(): float
    {
        return cache()->remember('avg_reading_speed', 1800, function () {
            // Assuming average story is ~1000 words
            $avgWordsPerStory = 1000;
            $avgTimeSpent = self::where('reading_progress', '>=', 100)
                ->avg('time_spent');
            
            if (!$avgTimeSpent || $avgTimeSpent <= 0) {
                return 250; // Default words per minute
            }

            return round(($avgWordsPerStory / ($avgTimeSpent / 60)), 2);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | MODEL EVENTS - ✅ IMPROVED with better performance and error handling
    |--------------------------------------------------------------------------
    */

    protected static function boot(): void
    {
        parent::boot();

        // ✅ IMPROVED: Better event handling with error catching
        static::updating(function ($model) {
            if ($model->isDirty(['reading_progress', 'time_spent'])) {
                $model->last_read_at = now();
            }
        });

        static::saved(function ($model) {
            try {
                // Clear related caches
                cache()->forget("member_reading_stats_{$model->member_id}");
                cache()->forget("story_reading_stats_{$model->story_id}");
                
                // Update story view if member exists and has device_id
                if ($model->member && $model->member->device_id) {
                    \App\Models\StoryView::updateOrCreate(
                        [
                            'story_id' => $model->story_id,
                            'member_id' => $model->member_id,
                        ],
                        [
                            'device_id' => $model->member->device_id,
                            'user_agent' => request()->header('User-Agent', 'Unknown'),
                            'ip_address' => request()->ip(),
                            'viewed_at' => now(),
                        ]
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Error in MemberReadingHistory saved event', [
                    'model_id' => $model->id,
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION - ✅ IMPROVED with comprehensive rules
    |--------------------------------------------------------------------------
    */

    public static function rules(): array
    {
        return [
            'member_id' => 'required|integer|exists:members,id',
            'story_id' => 'required|integer|exists:stories,id',
            'reading_progress' => 'required|numeric|min:0|max:100',
            'time_spent' => 'required|integer|min:0|max:86400', // Max 24 hours
            'last_read_at' => 'nullable|date',
        ];
    }

    public static function messages(): array
    {
        return [
            'member_id.exists' => 'The selected member does not exist.',
            'story_id.exists' => 'The selected story does not exist.',
            'reading_progress.max' => 'Reading progress cannot exceed 100%.',
            'time_spent.max' => 'Time spent cannot exceed 24 hours.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FILAMENT INTEGRATION - ✅ NEW for better admin interface
    |--------------------------------------------------------------------------
    */

    public function getFilamentName(): string
    {
        return $this->member?->name . ' → ' . $this->story?->title;
    }

    public function getProgressBadgeColor(): string
    {
        return match (true) {
            $this->reading_progress >= 100 => 'success',
            $this->reading_progress >= 75 => 'warning',
            $this->reading_progress >= 25 => 'info',
            $this->reading_progress > 0 => 'primary',
            default => 'gray'
        };
    }
}