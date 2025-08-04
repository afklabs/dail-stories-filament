<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * StoryRatingAggregate Model for Daily Stories App with Filament Integration
 * 
 * Enhanced aggregate system for story ratings with comprehensive analytics,
 * sentiment analysis, and performance optimizations.
 *
 * @property int $id
 * @property int $story_id
 * @property int $total_ratings
 * @property int $sum_ratings
 * @property float $average_rating
 * @property array $rating_distribution
 * @property int|null $verified_ratings_count
 * @property float|null $verified_average_rating
 * @property int|null $comments_count
 * @property Carbon|null $last_rated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read float $comments_percentage
 * @property-read float $high_rating_percentage
 * @property-read bool $is_high_quality
 * @property-read bool $is_reliable
 * @property-read float $low_rating_percentage
 * @property-read float $medium_rating_percentage
 * @property-read float $negative_rating_percentage
 * @property-read float $neutral_rating_percentage
 * @property-read float $positive_rating_percentage
 * @property-read float $quality_score
 * @property-read string $rating_level
 * @property-read array $rating_percentages
 * @property-read float $recommendation_rate
 * @property-read float $rounded_average
 * @property-read string $sentiment
 * @property-read string $stars
 * @property-read float $verified_percentage
 * @property-read Collection<int, \App\Models\MemberStoryRating> $ratings
 * @property-read int|null $ratings_count
 * @property-read \App\Models\Story $story
 * @method static Builder<static>|StoryRatingAggregate excellent()
 * @method static Builder<static>|StoryRatingAggregate highRated(float $minRating = 4)
 * @method static Builder<static>|StoryRatingAggregate mostRated()
 * @method static Builder<static>|StoryRatingAggregate newModelQuery()
 * @method static Builder<static>|StoryRatingAggregate newQuery()
 * @method static Builder<static>|StoryRatingAggregate query()
 * @method static Builder<static>|StoryRatingAggregate reliable()
 * @method static Builder<static>|StoryRatingAggregate trending(int $days = 7)
 * @method static Builder<static>|StoryRatingAggregate whereAverageRating($value)
 * @method static Builder<static>|StoryRatingAggregate whereCreatedAt($value)
 * @method static Builder<static>|StoryRatingAggregate whereId($value)
 * @method static Builder<static>|StoryRatingAggregate whereRatingDistribution($value)
 * @method static Builder<static>|StoryRatingAggregate whereStoryId($value)
 * @method static Builder<static>|StoryRatingAggregate whereSumRatings($value)
 * @method static Builder<static>|StoryRatingAggregate whereTotalRatings($value)
 * @method static Builder<static>|StoryRatingAggregate whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class StoryRatingAggregate extends Model
{
    use HasFactory;

    /**
     * Rating system constants
     */
    private const MIN_RATING = 1;

    private const MAX_RATING = 5;

    private const VALID_RATINGS = [1, 2, 3, 4, 5];

    private const HIGH_RATING_THRESHOLD = 4;

    private const LOW_RATING_THRESHOLD = 2;

    private const EXCELLENT_RATING = 5;

    private const GOOD_RATING = 4;

    private const AVERAGE_RATING = 3;

    private const POOR_RATING = 2;

    private const TERRIBLE_RATING = 1;

    /**
     * Cache constants
     */
    private const CACHE_TTL = 600; // 10 minutes

    private const CACHE_TTL_ANALYTICS = 1800; // 30 minutes

    private const CACHE_TTL_TRENDS = 3600; // 1 hour

    /**
     * Quality thresholds
     */
    private const MIN_RATINGS_FOR_RELIABILITY = 5;

    private const HIGH_QUALITY_THRESHOLD = 4.0;

    private const RECOMMENDATION_THRESHOLD = 4;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'story_id',
        'total_ratings',
        'sum_ratings',
        'average_rating',
        'rating_distribution',
        'verified_ratings_count',
        'verified_average_rating',
        'comments_count',
        'last_rated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'story_id' => 'integer',
        'total_ratings' => 'integer',
        'sum_ratings' => 'integer',
        'average_rating' => 'decimal:2',
        'rating_distribution' => 'array',
        'verified_ratings_count' => 'integer',
        'verified_average_rating' => 'decimal:2',
        'comments_count' => 'integer',
        'last_rated_at' => 'datetime',
    ];

    /**
     * Model boot method for event handling
     */
    protected static function boot(): void
    {
        parent::boot();

        // Clear related caches when aggregate is updated
        static::updated(function (self $aggregate): void {
            $aggregate->clearRelatedCache();
        });

        // Clear related caches when aggregate is deleted
        static::deleted(function (self $aggregate): void {
            $aggregate->clearRelatedCache();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the story that this aggregate belongs to.
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * Get the ratings that make up this aggregate.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(MemberStoryRating::class, 'story_id', 'story_id');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the average rating rounded to nearest half
     */
    public function getRoundedAverageAttribute(): float
    {
        return round($this->average_rating * 2) / 2;
    }

    /**
     * Get the average rating as stars string
     */
    public function getStarsAttribute(): string
    {
        $fullStars = floor($this->average_rating);
        $halfStar = ($this->average_rating - $fullStars) >= 0.5 ? 1 : 0;
        $emptyStars = 5 - $fullStars - $halfStar;

        return str_repeat('⭐', (int) $fullStars) .
            str_repeat('✨', (int) $halfStar) .
            str_repeat('☆', (int) $emptyStars);
    }

    /**
     * Get percentage for each rating level
     */
    public function getRatingPercentagesAttribute(): array
    {
        $percentages = [];

        if ($this->total_ratings > 0) {
            for ($i = self::MIN_RATING; $i <= self::MAX_RATING; $i++) {
                $count = $this->rating_distribution[$i] ?? 0;
                $percentages[$i] = round(($count / $this->total_ratings) * 100, 1);
            }
        } else {
            $percentages = array_fill_keys(self::VALID_RATINGS, 0);
        }

        return $percentages;
    }

    /**
     * Get quality score (0-100)
     */
    public function getQualityScoreAttribute(): float
    {
        if ($this->total_ratings < self::MIN_RATINGS_FOR_RELIABILITY) {
            return 0;
        }

        // Base score from average rating (0-80 points)
        $ratingScore = ($this->average_rating / self::MAX_RATING) * 80;

        // Volume bonus (0-20 points)
        $volumeBonus = min(($this->total_ratings / 100) * 20, 20);

        return round($ratingScore + $volumeBonus, 1);
    }

    /**
     * Get recommendation rate (percentage of 4-5 star ratings)
     */
    public function getRecommendationRateAttribute(): float
    {
        if ($this->total_ratings === 0) {
            return 0;
        }

        $recommendableRatings = ($this->rating_distribution[4] ?? 0) + ($this->rating_distribution[5] ?? 0);

        return round(($recommendableRatings / $this->total_ratings) * 100, 1);
    }

    /**
     * Get sentiment classification
     */
    public function getSentimentAttribute(): string
    {
        return match (true) {
            $this->average_rating >= 4.5 => 'excellent',
            $this->average_rating >= 4.0 => 'very_good',
            $this->average_rating >= 3.5 => 'good',
            $this->average_rating >= 3.0 => 'average',
            $this->average_rating >= 2.0 => 'poor',
            default => 'terrible',
        };
    }

    /**
     * Get rating level name in Arabic
     */
    public function getRatingLevelAttribute(): string
    {
        return match (true) {
            $this->average_rating >= 4.5 => 'ممتاز',
            $this->average_rating >= 4.0 => 'جيد جداً',
            $this->average_rating >= 3.5 => 'جيد',
            $this->average_rating >= 3.0 => 'متوسط',
            $this->average_rating >= 2.0 => 'ضعيف',
            default => 'سيء جداً',
        };
    }

    /**
     * Check if story has enough ratings for reliability
     */
    public function getIsReliableAttribute(): bool
    {
        return $this->total_ratings >= self::MIN_RATINGS_FOR_RELIABILITY;
    }

    /**
     * Check if story has high quality rating
     */
    public function getIsHighQualityAttribute(): bool
    {
        return $this->is_reliable && $this->average_rating >= self::HIGH_QUALITY_THRESHOLD;
    }

    /**
     * Get verified rating percentage
     */
    public function getVerifiedPercentageAttribute(): float
    {
        if ($this->total_ratings === 0) {
            return 0;
        }

        return round((($this->verified_ratings_count ?? 0) / $this->total_ratings) * 100, 1);
    }

    /**
     * Get comments percentage
     */
    public function getCommentsPercentageAttribute(): float
    {
        if ($this->total_ratings === 0) {
            return 0;
        }

        return round((($this->comments_count ?? 0) / $this->total_ratings) * 100, 1);
    }

    /*
    |--------------------------------------------------------------------------
    | ENHANCED TYPE-SAFE RATING METHODS - NEW ADDITIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Get high rating percentage (4-5 stars) with proper type casting
     * 
     * FIXED: Ensures proper type comparison and handles null/undefined values
     */
    public function getHighRatingPercentageAttribute(): float
    {
        $totalRatings = (int) $this->total_ratings;
        if ($totalRatings === 0) {
            return 0.0;
        }

        // Safely cast rating counts to integers
        $rating4Count = (int) ($this->rating_distribution[4] ?? 0);
        $rating5Count = (int) ($this->rating_distribution[5] ?? 0);

        $highRatings = $rating4Count + $rating5Count;

        return round(($highRatings / $totalRatings) * 100, 1);
    }

    /**
     * Get low rating percentage (1-2 stars) with proper type casting
     * 
     * FIXED: Ensures proper type comparison and handles null/undefined values
     */
    public function getLowRatingPercentageAttribute(): float
    {
        $totalRatings = (int) $this->total_ratings;
        if ($totalRatings === 0) {
            return 0.0;
        }

        // Safely cast rating counts to integers
        $rating1Count = (int) ($this->rating_distribution[1] ?? 0);
        $rating2Count = (int) ($this->rating_distribution[2] ?? 0);

        $lowRatings = $rating1Count + $rating2Count;

        return round(($lowRatings / $totalRatings) * 100, 1);
    }

    /**
     * Get medium rating percentage (3 stars) with proper type casting
     */
    public function getMediumRatingPercentageAttribute(): float
    {
        $totalRatings = (int) $this->total_ratings;
        if ($totalRatings === 0) {
            return 0.0;
        }

        $rating3Count = (int) ($this->rating_distribution[3] ?? 0);

        return round(($rating3Count / $totalRatings) * 100, 1);
    }

    /**
     * Get positive rating percentage (4-5 stars) - alias for high ratings
     */
    public function getPositiveRatingPercentageAttribute(): float
    {
        return $this->high_rating_percentage;
    }

    /**
     * Get negative rating percentage (1-2 stars) - alias for low ratings
     */
    public function getNegativeRatingPercentageAttribute(): float
    {
        return $this->low_rating_percentage;
    }

    /**
     * Get neutral rating percentage (3 stars) - alias for medium ratings
     */
    public function getNeutralRatingPercentageAttribute(): float
    {
        return $this->medium_rating_percentage;
    }

    /**
     * Get individual rating count with type safety
     */
    public function getRatingCount(int $rating): int
    {
        if ($rating < self::MIN_RATING || $rating > self::MAX_RATING) {
            return 0;
        }

        return (int) ($this->rating_distribution[$rating] ?? 0);
    }

    /**
     * Get rating distribution with type safety
     */
    public function getSafeRatingDistribution(): array
    {
        $distribution = [];

        for ($i = self::MIN_RATING; $i <= self::MAX_RATING; $i++) {
            $distribution[$i] = (int) ($this->rating_distribution[$i] ?? 0);
        }

        return $distribution;
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for high rated stories
     */
    public function scopeHighRated(Builder $query, float $minRating = 4.0): Builder
    {
        return $query->where('average_rating', '>=', $minRating);
    }

    /**
     * Scope for reliable ratings (minimum threshold)
     */
    public function scopeReliable(Builder $query): Builder
    {
        return $query->where('total_ratings', '>=', self::MIN_RATINGS_FOR_RELIABILITY);
    }

    /**
     * Scope for excellent stories
     */
    public function scopeExcellent(Builder $query): Builder
    {
        return $query->where('average_rating', '>=', 4.5);
    }

    /**
     * Scope for most rated stories
     */
    public function scopeMostRated(Builder $query): Builder
    {
        return $query->orderByDesc('total_ratings');
    }

    /**
     * Scope for trending stories (high recent activity)
     */
    public function scopeTrending(Builder $query, int $days = 7): Builder
    {
        return $query->where('last_rated_at', '>=', Carbon::now()->subDays($days))
            ->where('total_ratings', '>=', 3)
            ->orderByDesc('average_rating');
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC METHODS FOR AGGREGATE MANAGEMENT
    |--------------------------------------------------------------------------
    */

    /**
     * Update or create aggregate for a story
     */
    public static function updateStoryAggregate(int $storyId): void
    {
        try {
            $ratings = MemberStoryRating::where('story_id', $storyId)->get();

            $totalRatings = $ratings->count();
            $sumRatings = $ratings->sum('rating');
            $averageRating = $totalRatings > 0 ? round($sumRatings / $totalRatings, 2) : 0;

            // Distribution calculation with type safety
            $distribution = [];
            for ($i = self::MIN_RATING; $i <= self::MAX_RATING; $i++) {
                $distribution[$i] = (int) $ratings->where('rating', $i)->count();
            }

            // Enhanced metrics
            $verifiedRatings = $ratings->where('is_verified', true);
            $ratingsWithComments = $ratings->filter(fn($r) => ! empty(trim($r->comment ?? '')));

            self::updateOrCreate(
                ['story_id' => $storyId],
                [
                    'total_ratings' => (int) $totalRatings,
                    'sum_ratings' => (int) $sumRatings,
                    'average_rating' => (float) $averageRating,
                    'rating_distribution' => $distribution,
                    'verified_ratings_count' => (int) $verifiedRatings->count(),
                    'verified_average_rating' => $verifiedRatings->count() > 0
                        ? round($verifiedRatings->avg('rating'), 2)
                        : null,
                    'comments_count' => (int) $ratingsWithComments->count(),
                    'last_rated_at' => $ratings->max('created_at'),
                ]
            );

            // Clear related caches
            self::clearStoryCache($storyId);
        } catch (\Exception $e) {
            Log::error('Failed to update story rating aggregate', [
                'story_id' => $storyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get comprehensive rating analytics for a story
     */
    public static function getStoryAnalytics(int $storyId): array
    {
        return Cache::remember("story_rating_analytics_{$storyId}", self::CACHE_TTL_ANALYTICS, function () use ($storyId): array {
            $aggregate = self::where('story_id', $storyId)->first();

            if (! $aggregate) {
                return self::getDefaultAnalytics();
            }

            return [
                'basic_stats' => [
                    'average_rating' => (float) $aggregate->average_rating,
                    'total_ratings' => (int) $aggregate->total_ratings,
                    'quality_score' => (float) $aggregate->quality_score,
                    'recommendation_rate' => (float) $aggregate->recommendation_rate,
                    'is_reliable' => (bool) $aggregate->is_reliable,
                    'is_high_quality' => (bool) $aggregate->is_high_quality,
                ],
                'distribution' => [
                    'counts' => $aggregate->safe_rating_distribution,
                    'percentages' => $aggregate->rating_percentages,
                ],
                'quality_metrics' => [
                    'verified_count' => (int) ($aggregate->verified_ratings_count ?? 0),
                    'verified_average' => (float) ($aggregate->verified_average_rating ?? 0),
                    'verified_percentage' => (float) $aggregate->verified_percentage,
                    'comments_count' => (int) ($aggregate->comments_count ?? 0),
                    'comments_percentage' => (float) $aggregate->comments_percentage,
                ],
                'sentiment_analysis' => [
                    'sentiment' => $aggregate->sentiment,
                    'rating_level' => $aggregate->rating_level,
                    'positive_percentage' => (float) $aggregate->high_rating_percentage,
                    'negative_percentage' => (float) $aggregate->low_rating_percentage,
                    'neutral_percentage' => (float) $aggregate->medium_rating_percentage,
                ],
                'rating_breakdown' => [
                    'high_ratings' => (float) $aggregate->high_rating_percentage,
                    'medium_ratings' => (float) $aggregate->medium_rating_percentage,
                    'low_ratings' => (float) $aggregate->low_rating_percentage,
                    'positive_ratings' => (float) $aggregate->positive_rating_percentage,
                    'negative_ratings' => (float) $aggregate->negative_rating_percentage,
                    'neutral_ratings' => (float) $aggregate->neutral_rating_percentage,
                ],
                'recent_activity' => [
                    'last_rated_at' => $aggregate->last_rated_at,
                    'recent_ratings' => self::getRecentRatings($storyId),
                ],
            ];
        });
    }

    /**
     * Get top rated stories with analytics
     */
    public static function getTopRatedStories(int $limit = 10): Collection
    {
        return Cache::remember("top_rated_stories_{$limit}", self::CACHE_TTL_ANALYTICS, function () use ($limit): Collection {
            return self::with('story')
                ->reliable()
                ->highRated()
                ->orderByDesc('average_rating')
                ->orderByDesc('total_ratings')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get most rated stories
     */
    public static function getMostRatedStories(int $limit = 10): Collection
    {
        return Cache::remember("most_rated_stories_{$limit}", self::CACHE_TTL_ANALYTICS, function () use ($limit): Collection {
            return self::with('story')
                ->mostRated()
                ->orderByDesc('average_rating')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get trending stories based on recent ratings
     */
    public static function getTrendingStories(int $days = 7, int $limit = 10): Collection
    {
        return Cache::remember("trending_stories_{$days}_{$limit}", self::CACHE_TTL, function () use ($days, $limit): Collection {
            return self::with('story')
                ->trending($days)
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get rating trends for a story
     */
    public static function getRatingTrends(int $storyId, int $days = 30): array
    {
        return Cache::remember("story_rating_trends_{$storyId}_{$days}", self::CACHE_TTL_TRENDS, function () use ($storyId, $days): array {
            $ratings = MemberStoryRating::where('story_id', $storyId)
                ->where('created_at', '>=', Carbon::now()->subDays($days))
                ->orderBy('created_at')
                ->get()
                ->groupBy(fn($rating) => $rating->created_at->format('Y-m-d'));

            $trends = [];
            foreach ($ratings as $date => $dayRatings) {
                $trends[$date] = [
                    'date' => $date,
                    'count' => (int) $dayRatings->count(),
                    'average' => round($dayRatings->avg('rating'), 2),
                    'total_sum' => (int) $dayRatings->sum('rating'),
                ];
            }

            return $trends;
        });
    }

    /**
     * Get global rating statistics
     */
    public static function getGlobalStats(): array
    {
        return Cache::remember('global_rating_stats', self::CACHE_TTL_ANALYTICS, function (): array {
            $totalStories = (int) self::count();
            $totalRatings = (int) self::sum('total_ratings');
            $averageGlobalRating = (float) self::avg('average_rating');

            return [
                'total_stories_rated' => $totalStories,
                'total_ratings_given' => $totalRatings,
                'global_average_rating' => round($averageGlobalRating ?? 0, 2),
                'high_quality_stories' => (int) self::reliable()->highRated()->count(),
                'excellent_stories' => (int) self::excellent()->count(),
                'stories_with_comments' => (int) self::where('comments_count', '>', 0)->count(),
                'verified_ratings_total' => (int) self::sum('verified_ratings_count'),
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | UTILITY METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Rebuild aggregate from scratch
     */
    public function rebuildAggregate(): void
    {
        self::updateStoryAggregate($this->story_id);
    }

    /**
     * Get recent ratings for display
     */
    private static function getRecentRatings(int $storyId, int $limit = 5): array
    {
        return MemberStoryRating::where('story_id', $storyId)
            ->with(['member:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($rating) {
                return [
                    'rating' => (int) $rating->rating,
                    'comment' => $rating->comment,
                    'member_name' => $rating->member?->name ?? 'Anonymous',
                    'created_at' => $rating->created_at,
                    'relative_time' => $rating->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    /**
     * Get default analytics for stories without ratings
     */
    private static function getDefaultAnalytics(): array
    {
        return [
            'basic_stats' => [
                'average_rating' => 0.0,
                'total_ratings' => 0,
                'quality_score' => 0.0,
                'recommendation_rate' => 0.0,
                'is_reliable' => false,
                'is_high_quality' => false,
            ],
            'distribution' => [
                'counts' => array_fill_keys(self::VALID_RATINGS, 0),
                'percentages' => array_fill_keys(self::VALID_RATINGS, 0.0),
            ],
            'quality_metrics' => [
                'verified_count' => 0,
                'verified_average' => 0.0,
                'verified_percentage' => 0.0,
                'comments_count' => 0,
                'comments_percentage' => 0.0,
            ],
            'sentiment_analysis' => [
                'sentiment' => 'unrated',
                'rating_level' => 'غير مقيم',
                'positive_percentage' => 0.0,
                'negative_percentage' => 0.0,
                'neutral_percentage' => 0.0,
            ],
            'rating_breakdown' => [
                'high_ratings' => 0.0,
                'medium_ratings' => 0.0,
                'low_ratings' => 0.0,
                'positive_ratings' => 0.0,
                'negative_ratings' => 0.0,
                'neutral_ratings' => 0.0,
            ],
            'recent_activity' => [
                'last_rated_at' => null,
                'recent_ratings' => [],
            ],
        ];
    }

    /**
     * Clear all related caches
     */
    public function clearRelatedCache(): void
    {
        self::clearStoryCache($this->story_id);
    }

    /**
     * Clear story-specific caches
     */
    private static function clearStoryCache(int $storyId): void
    {
        $patterns = [
            "story_rating_analytics_{$storyId}",
            "story_rating_trends_{$storyId}_30",
            "story_rating_trends_{$storyId}_7",
            'top_rated_stories_10',
            'most_rated_stories_10',
            'trending_stories_7_10',
            'global_rating_stats',
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Get Filament-friendly display name
     */
    public function getFilamentName(): string
    {
        $storyTitle = $this->story?->title ?? 'Unknown Story';

        return "{$storyTitle} ({$this->average_rating} ⭐ - {$this->total_ratings} ratings)";
    }

    /**
     * Get validation rules for aggregate data
     *
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'story_id' => 'required|exists:stories,id',
            'total_ratings' => 'required|integer|min:0',
            'sum_ratings' => 'required|integer|min:0',
            'average_rating' => 'required|numeric|between:0,5',
            'rating_distribution' => 'required|array',
            'verified_ratings_count' => 'nullable|integer|min:0',
            'verified_average_rating' => 'nullable|numeric|between:0,5',
            'comments_count' => 'nullable|integer|min:0',
            'last_rated_at' => 'nullable|date',
        ];
    }
}
