<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Story Model for Daily Stories App with Filament Integration
 *
 * @property int $id
 * @property string $title
 * @property string $content
 * @property string|null $excerpt
 * @property int $category_id
 * @property int $views
 * @property int $reading_time_minutes
 * @property bool $active
 * @property bool $is_featured
 * @property Carbon|null $active_from
 * @property Carbon|null $active_until
 * @property string|null $image
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property-read Category|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection|Tag[] $tags
 * @property-read \Illuminate\Database\Eloquent\Collection|StoryView[] $storyViews
 * @property-read \Illuminate\Database\Eloquent\Collection|MemberStoryInteraction[] $interactions
 * @property-read \Illuminate\Database\Eloquent\Collection|MemberStoryRating[] $ratings
 * @property-read StoryRatingAggregate|null $ratingAggregate
 * @property-read \Illuminate\Database\Eloquent\Collection|MemberReadingHistory[] $readingHistory
 * @property-read \Illuminate\Database\Eloquent\Collection|StoryPublishingHistory[] $publishingHistory
 */
class Story extends Model
{
    use HasFactory;

    /**
     * Constants for cache and validation
     */
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_TTL_SHORT = 300; // 5 minutes for stats
    private const CACHE_TTL_LONG = 1800; // 30 minutes for heavy analytics
    private const MIN_EXCERPT_LENGTH = 50;
    private const MAX_EXCERPT_LENGTH = 500;
    private const AVG_READING_SPEED = 200; // words per minute
    private const URGENT_HOURS = 1;
    private const WARNING_HOURS = 6;

    /**
     * Rating system constants
     */
    private const MIN_RATING = 1;
    private const MAX_RATING = 5;
    private const HIGH_RATING_THRESHOLD = 4;
    private const LOW_RATING_THRESHOLD = 2;

    /**
     * Interaction types
     */
    private const POSITIVE_INTERACTIONS = ['like', 'bookmark', 'share'];
    private const NEGATIVE_INTERACTIONS = ['dislike', 'report'];
    private const NEUTRAL_INTERACTIONS = ['view'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'content',
        'excerpt',
        'category_id',
        'views',
        'reading_time_minutes',
        'active',
        'is_featured',
        'active_from',
        'active_until',
        'image',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
        'is_featured' => 'boolean',
        'active_from' => 'datetime',
        'active_until' => 'datetime',
        'views' => 'integer',
        'reading_time_minutes' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * The attributes that should be appended to arrays.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'image_url',
        'is_expired',
        'status',
    ];

    /*
    |--------------------------------------------------------------------------
    | MODEL EVENTS
    |--------------------------------------------------------------------------
    */

    /**
     * Model boot method for event handling
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate excerpt and reading time before saving
        static::saving(function (self $story): void {
            // Generate excerpt if not provided
            if (empty($story->excerpt)) {
                $story->excerpt = $story->generateExcerpt();
            }

            // Calculate reading time if not set or is zero
            if ($story->reading_time_minutes <= 0) {
                $story->reading_time_minutes = $story->calculateReadingTime();
            }

            // Validate excerpt length
            $story->excerpt = Str::limit($story->excerpt, self::MAX_EXCERPT_LENGTH);
        });

        // Clear enhanced cache when story is updated
        static::updated(function (self $story): void {
            $story->clearEnhancedCache();
        });

        // Clear enhanced cache when story is deleted
        static::deleted(function (self $story): void {
            $story->clearEnhancedCache();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the category that owns the story.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the tags associated with the story.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'story_tags')
            ->withTimestamps();
    }

    /**
     * Get the story views.
     */
    public function storyViews(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    /**
     * Get the story interactions.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(MemberStoryInteraction::class);
    }

    /**
     * Get the story ratings.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(MemberStoryRating::class);
    }

    /**
     * Get the story rating aggregate.
     */
    public function ratingAggregate(): HasOne
    {
        return $this->hasOne(StoryRatingAggregate::class);
    }

    /**
     * Get the reading history records.
     */
    public function readingHistory(): HasMany
    {
        return $this->hasMany(MemberReadingHistory::class);
    }

    /**
     * Get the publishing history records.
     */
    public function publishingHistory(): HasMany
    {
        return $this->hasMany(StoryPublishingHistory::class)
            ->orderBy('created_at', 'desc');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the story's image URL
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        // Check if it's already a full URL
        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }

    /**
     * Get sanitized excerpt with auto-generation fallback
     */
    public function getExcerptAttribute(?string $value): string
    {
        if (!empty($value)) {
            return $value;
        }
        // Auto-generate from content
        $text = strip_tags($this->content ?? '');
        return Str::limit($text, 150);
    }

    /**
     * Set excerpt with sanitization
     */
    public function setExcerptAttribute(?string $value): void
    {
        $this->attributes['excerpt'] = $value ? Str::limit(strip_tags($value), self::MAX_EXCERPT_LENGTH) : null;
    }

    /**
     * Get story status based on active state and dates
     */
    public function getStatusAttribute(): string
    {
        if (!$this->active) {
            return 'draft';
        }

        $now = now();

        if ($this->active_from && $this->active_from > $now) {
            return 'scheduled';
        }

        if ($this->active_until && $this->active_until < $now) {
            return 'expired';
        }

        return 'published';
    }

    /**
     * Check if story is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->active_until && $this->active_until->isPast();
    }

    /**
     * Get remaining time until expiry
     */
    public function getRemainingTimeAttribute(): ?array
    {
        if (!$this->active_until) {
            return null;
        }

        $now = now();
        if ($this->active_until->isPast()) {
            return null;
        }

        $diff = $now->diff($this->active_until);

        return [
            'hours' => $diff->h + ($diff->days * 24),
            'minutes' => $diff->i,
            'seconds' => $diff->s,
        ];
    }

    /**
     * Get display excerpt (public method for consistency with Dart model)
     */
    public function getDisplayExcerptAttribute(): string
    {
        return $this->excerpt; // This will automatically use the accessor above
    }

    /**
     * Get calculated reading time (uses stored value or calculates from content)
     */
    public function getCalculatedReadingTimeMinutesAttribute(): int
    {
        if ($this->reading_time_minutes > 0) {
            return $this->reading_time_minutes;
        }

        return $this->calculateReadingTime();
    }

    /**
     * Get formatted reading time
     */
    public function getFormattedReadingTimeAttribute(): string
    {
        $time = $this->calculated_reading_time_minutes;
        return $time . ' دقيقة قراءة';
    }

    /**
     * Get average rating (cached) - FIXED: Now returns float
     */
    public function getAverageRatingAttribute(): float
    {
        return (float) Cache::remember(
            "story.{$this->id}.avg_rating",
            self::CACHE_TTL,
            fn() => $this->ratingAggregate?->average_rating ?? 0.0
        );
    }

    /**
     * Get total ratings (cached)
     */
    public function getTotalRatingsAttribute(): int
    {
        return (int) Cache::remember(
            "story.{$this->id}.total_ratings",
            self::CACHE_TTL,
            fn() => $this->ratingAggregate?->total_ratings ?? 0
        );
    }

    /**
     * Get formatted views count
     */
    public function getFormattedViewsAttribute(): string
    {
        return $this->formatNumber($this->views);
    }

    /**
     * Get formatted ratings count
     */
    public function getFormattedTotalRatingsAttribute(): string
    {
        return $this->formatNumber($this->total_ratings);
    }

    /**
     * Check if story has expired (alias for consistency)
     */
    public function getHasExpiredAttribute(): bool
    {
        return $this->is_expired;
    }

    /**
     * Get formatted remaining time string
     */
    public function getFormattedRemainingTimeAttribute(): string
    {
        $remaining = $this->remaining_time;
        if (!$remaining) {
            return '';
        }

        return $this->formatRemainingTime(0, $remaining['hours'], $remaining['minutes'], $remaining['seconds']);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for active stories
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(function (Builder $q) {
                $q->whereNull('active_from')
                    ->orWhere('active_from', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('active_until')
                    ->orWhere('active_until', '>', now());
            });
    }

    /**
     * Scope for published stories (alias for active)
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->active();
    }

    /**
     * Scope for scheduled stories
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where('active_from', '>', now());
    }

    /**
     * Scope for expired stories
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('active', true)
            ->whereNotNull('active_until')
            ->where('active_until', '<=', now());
    }

    /**
     * Scope for featured stories
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for stories by category
     */
    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope for stories by tag
     */
    public function scopeWithTag(Builder $query, int $tagId): Builder
    {
        return $query->whereHas('tags', function (Builder $q) use ($tagId) {
            $q->where('tags.id', $tagId);
        });
    }

    /**
     * Scope for popular stories
     */
    public function scopePopular(Builder $query, int $minViews = 100): Builder
    {
        return $query->where('views', '>=', $minViews)
            ->orderByDesc('views');
    }

    /**
     * Scope for recent stories
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for high rated stories
     */
    public function scopeHighRated(Builder $query, float $minRating = 4.0): Builder
    {
        return $query->whereHas('ratingAggregate', function (Builder $q) use ($minRating) {
            $q->where('average_rating', '>=', $minRating);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Generate excerpt from content
     */
    public function generateExcerpt(): string
    {
        $plainText = strip_tags($this->content ?? '');
        $plainText = preg_replace('/\s+/', ' ', $plainText);
        $plainText = trim($plainText);

        // Find a good break point (end of sentence if possible)
        $excerpt = Str::limit($plainText, self::MAX_EXCERPT_LENGTH, '');
        
        // Try to end at a sentence
        $lastPeriod = strrpos($excerpt, '.');
        $lastQuestion = strrpos($excerpt, '?');
        $lastExclamation = strrpos($excerpt, '!');
        
        $lastSentenceEnd = max($lastPeriod, $lastQuestion, $lastExclamation);
        
        if ($lastSentenceEnd !== false && $lastSentenceEnd > self::MIN_EXCERPT_LENGTH) {
            $excerpt = substr($excerpt, 0, $lastSentenceEnd + 1);
        } else {
            $excerpt .= '...';
        }

        return $excerpt;
    }

    /**
     * Calculate reading time based on content
     */
    public function calculateReadingTime(): int
    {
        $wordCount = str_word_count(strip_tags($this->content ?? ''));
        $readingTime = (int) ceil($wordCount / self::AVG_READING_SPEED);
        
        // Minimum 1 minute
        return max($readingTime, 1);
    }

    /**
     * Record a view with device tracking
     */
    public function recordView(?string $deviceId = null, ?int $memberId = null): void
    {
        try {
            // Create view record
            $this->storyViews()->create([
                'device_id' => $deviceId,
                'member_id' => $memberId,
                'session_id' => session()->getId(),
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
                'referrer' => request()->header('referer'),
                'viewed_at' => now(),
            ]);

            // Increment view counter
            $this->increment('views');
            
            // Clear view-related caches
            Cache::forget("story.{$this->id}.stats");
            Cache::forget("story.{$this->id}.analytics");
        } catch (\Exception $e) {
            \Log::error('Failed to record story view', [
                'story_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if member has viewed this story
     */
    public function hasMemberViewed(int $memberId): bool
    {
        return Cache::remember(
            "story.{$this->id}.member.{$memberId}.viewed",
            self::CACHE_TTL,
            fn() => $this->storyViews()->where('member_id', $memberId)->exists()
        );
    }

    /**
     * Check if member has specific interaction
     */
    public function hasMemberInteraction(int $memberId, string $action): bool
    {
        return Cache::remember(
            "story.{$this->id}.member.{$memberId}.interaction.{$action}",
            self::CACHE_TTL,
            fn() => $this->interactions()
                ->where('member_id', $memberId)
                ->where('action', $action)
                ->exists()
        );
    }

    /**
     * Get member's rating for this story
     */
    public function getMemberRating(int $memberId): ?int
    {
        return Cache::remember(
            "story.{$this->id}.member.{$memberId}.rating",
            self::CACHE_TTL,
            function () use ($memberId): ?int {
                $rating = $this->ratings()
                    ->where('member_id', $memberId)
                    ->first();

                return $rating?->rating;
            }
        );
    }

    /**
     * Check if story should be highlighted (featured or expiring soon)
     */
    public function shouldHighlight(): bool
    {
        if ($this->is_featured) {
            return true;
        }

        $remaining = $this->remaining_time;
        return $remaining && isset($remaining['hours']) && $remaining['hours'] < self::WARNING_HOURS;
    }

    /**
     * Format story for API response (cached)
     */
    public function formatForApi(?int $memberId = null): array
    {
        $cacheKey = "story.{$this->id}.formatted" . ($memberId ? ".member.{$memberId}" : '');

        return Cache::remember($cacheKey, self::CACHE_TTL_SHORT, function () use ($memberId): array {
            $data = [
                'id' => $this->id,
                'title' => $this->title,
                'excerpt' => $this->excerpt,
                'content' => $this->content,
                'image_url' => $this->image_url,
                'category_id' => $this->category_id,
                'category_name' => $this->category?->name ?? '',
                'tags' => $this->tags->map(fn($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ]),
                'views' => $this->views,
                'reading_time_minutes' => $this->reading_time_minutes,
                'is_featured' => $this->is_featured,
                'is_published' => $this->active,
                'active_from' => $this->active_from?->toIso8601String(),
                'active_until' => $this->active_until?->toIso8601String(),
                'published_at' => $this->active_from?->toIso8601String(),
                'created_at' => $this->created_at->toIso8601String(),
                'updated_at' => $this->updated_at->toIso8601String(),
            ];

            // Add expiry information
            if ($this->active_until) {
                $data['expires_at'] = $this->active_until->toIso8601String();
                $data['is_expired'] = $this->is_expired;
                $data['has_expired'] = $this->has_expired;
                $data['remaining_time'] = $this->remaining_time;
            }

            // Add rating information
            $data['average_rating'] = round($this->average_rating, 1);
            $data['total_ratings'] = $this->total_ratings;

            // Add member-specific data
            if ($memberId) {
                $memberRating = $this->getMemberRating($memberId);
                $data['member_interactions'] = [
                    'has_viewed' => $this->hasMemberViewed($memberId),
                    'has_bookmarked' => $this->hasMemberInteraction($memberId, 'bookmark'),
                    'has_shared' => $this->hasMemberInteraction($memberId, 'share'),
                    'has_rated' => !is_null($memberRating),
                    'rating' => $memberRating,
                ];
            }

            return $data;
        });
    }

    /**
     * Clear all caches for this story
     */
    public function clearCache(): void
    {
        $patterns = [
            "story.{$this->id}",
            "story.{$this->id}.formatted",
            "story.{$this->id}.avg_rating",
            "story.{$this->id}.total_ratings",
            "story.{$this->id}.stats",
            "story.{$this->id}.analytics",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }

        // Clear member-specific caches
        $memberIds = $this->ratings()->pluck('member_id')->unique();
        foreach ($memberIds as $memberId) {
            Cache::forget("story.{$this->id}.member.{$memberId}.*");
        }
    }

    /**
     * Clear enhanced caches (includes related models)
     */
    public function clearEnhancedCache(): void
    {
        $this->clearCache();
        
        // Clear category cache
        if ($this->category_id) {
            Cache::forget("category.{$this->category_id}.stories");
        }

        // Clear tag caches
        foreach ($this->tags as $tag) {
            Cache::forget("tag.{$tag->id}.stories");
        }

        // Clear aggregate cache
        Cache::forget("story_rating_aggregate_{$this->id}");
    }

    /**
     * Get comprehensive analytics
     */
    public function getAnalytics(int $days = 30): array
    {
        $cacheKey = "story.{$this->id}.analytics.{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL_LONG, function () use ($days): array {
            $startDate = now()->subDays($days);

            return [
                'basic_stats' => [
                    'total_views' => $this->views,
                    'unique_views' => $this->storyViews()->distinct('device_id')->count(),
                    'member_views' => $this->storyViews()->whereNotNull('member_id')->count(),
                    'average_rating' => $this->average_rating,
                    'total_ratings' => $this->total_ratings,
                ],
                'period_stats' => [
                    'views_in_period' => $this->storyViews()->where('viewed_at', '>=', $startDate)->count(),
                    'ratings_in_period' => $this->ratings()->where('created_at', '>=', $startDate)->count(),
                    'shares_in_period' => $this->interactions()->where('action', 'share')->where('created_at', '>=', $startDate)->count(),
                ],
                'engagement' => [
                    'bookmark_rate' => $this->calculateEngagementRate('bookmark'),
                    'share_rate' => $this->calculateEngagementRate('share'),
                    'rating_rate' => $this->calculateRatingRate(),
                    'completion_rate' => $this->calculateCompletionRate(),
                ],
                'demographics' => $this->getDemographicAnalytics(),
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Format numbers with K/M suffixes
     */
    private function formatNumber(int $number): string
    {
        if ($number >= 1000000) {
            return number_format($number / 1000000, 1) . 'M';
        }

        if ($number >= 1000) {
            return number_format($number / 1000, 1) . 'K';
        }

        return (string) $number;
    }

    /**
     * Format remaining time to human readable
     */
    private function formatRemainingTime(int $days, int $hours, int $minutes, int $seconds): string
    {
        if ($days > 0) {
            return "{$days} يوم و {$hours} ساعة";
        }
        
        if ($hours > 0) {
            return "{$hours} ساعة و {$minutes} دقيقة";
        }
        
        if ($minutes > 0) {
            return "{$minutes} دقيقة";
        }
        
        return "{$seconds} ثانية";
    }

    /**
     * Calculate engagement rate for specific action
     */
    private function calculateEngagementRate(string $action): float
    {
        if ($this->views === 0) {
            return 0.0;
        }

        $interactions = $this->interactions()->where('action', $action)->count();
        return round(($interactions / $this->views) * 100, 2);
    }

    /**
     * Calculate rating rate
     */
    private function calculateRatingRate(): float
    {
        if ($this->views === 0) {
            return 0.0;
        }

        return round(($this->total_ratings / $this->views) * 100, 2);
    }

    /**
     * Calculate completion rate
     */
    private function calculateCompletionRate(): float
    {
        $totalReads = $this->readingHistory()->count();
        if ($totalReads === 0) {
            return 0.0;
        }

        $completedReads = $this->readingHistory()
            ->where('reading_progress', '>=', 100)
            ->count();

        return round(($completedReads / $totalReads) * 100, 2);
    }

    /**
     * Get demographic analytics
     */
    private function getDemographicAnalytics(): array
    {
        return Cache::remember(
            "story.{$this->id}.demographics",
            self::CACHE_TTL_LONG,
            function (): array {
                $viewers = $this->storyViews()
                    ->with('member:id,gender,date_of_birth')
                    ->whereNotNull('member_id')
                    ->get();

                $genderStats = $viewers->groupBy('member.gender')->map->count();
                
                $ageGroups = $viewers->map(function ($view) {
                    if (!$view->member || !$view->member->date_of_birth) {
                        return 'unknown';
                    }
                    
                    $age = $view->member->date_of_birth->age;
                    return match (true) {
                        $age < 18 => 'under_18',
                        $age <= 24 => '18_24',
                        $age <= 34 => '25_34',
                        $age <= 44 => '35_44',
                        $age <= 54 => '45_54',
                        default => '55_plus',
                    };
                })->countBy();

                return [
                    'gender_distribution' => $genderStats->toArray(),
                    'age_distribution' => $ageGroups->toArray(),
                ];
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Get trending stories based on recent activity
     */
    public static function getTrending(int $limit = 10, int $days = 7): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "trending_stories_{$limit}_{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($limit, $days) {
            return static::active()
                ->withCount([
                    'storyViews as recent_views' => function ($query) use ($days) {
                        $query->where('viewed_at', '>=', now()->subDays($days));
                    },
                    'ratings as recent_ratings' => function ($query) use ($days) {
                        $query->where('created_at', '>=', now()->subDays($days));
                    }
                ])
                ->orderByDesc('recent_views')
                ->orderByDesc('recent_ratings')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get recommended stories for a member
     */
    public static function getRecommended(int $memberId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "recommended_stories_{$memberId}_{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SHORT, function () use ($memberId, $limit) {
            // Get member's preferred categories
            $preferredCategories = MemberReadingHistory::where('member_id', $memberId)
                ->join('stories', 'member_reading_history.story_id', '=', 'stories.id')
                ->select('stories.category_id')
                ->groupBy('stories.category_id')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(3)
                ->pluck('category_id');

            // Get unread stories from preferred categories
            $readStoryIds = MemberReadingHistory::where('member_id', $memberId)
                ->pluck('story_id');

            return static::active()
                ->whereNotIn('id', $readStoryIds)
                ->when($preferredCategories->isNotEmpty(), function ($query) use ($preferredCategories) {
                    $query->whereIn('category_id', $preferredCategories);
                })
                ->highRated()
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        });
    }
}