<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class MemberStoryRating extends Model
{
    use HasFactory;

    // ✅ IMPROVED: Rating system constants for validation
    public const MIN_RATING = 1;

    public const MAX_RATING = 5;

    public const VALID_RATINGS = [1, 2, 3, 4, 5];

    // ✅ IMPROVED: Rating classification constants
    public const HIGH_RATING_THRESHOLD = 4;

    public const LOW_RATING_THRESHOLD = 2;

    public const NEUTRAL_RATING = 3;

    protected $fillable = [
        'member_id',
        'story_id',
        'rating',
        'comment',
        'is_verified', // ✅ NEW: For verified ratings
        'helpful_count', // ✅ NEW: For rating helpfulness
    ];

    // ✅ IMPROVED: Enhanced casting with better types
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'member_id' => 'integer',
        'story_id' => 'integer',
        'rating' => 'integer',
        'is_verified' => 'boolean',
        'helpful_count' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS - ✅ OPTIMIZED with better performance
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

    public function aggregate(): BelongsTo
    {
        return $this->belongsTo(StoryRatingAggregate::class, 'story_id', 'story_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES - ✅ IMPROVED with comprehensive filtering options
    |--------------------------------------------------------------------------
    */

    public function scopeByMember(Builder $query, int $memberId): Builder
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeByStory(Builder $query, int $storyId): Builder
    {
        return $query->where('story_id', $storyId);
    }

    public function scopeByRating(Builder $query, int $rating): Builder
    {
        return $query->where('rating', $rating);
    }

    public function scopeWithComments(Builder $query): Builder
    {
        return $query->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->where('comment', '!=', ' ');
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeHighRatings(Builder $query): Builder
    {
        return $query->where('rating', '>=', self::HIGH_RATING_THRESHOLD);
    }

    public function scopeLowRatings(Builder $query): Builder
    {
        return $query->where('rating', '<=', self::LOW_RATING_THRESHOLD);
    }

    // ✅ NEW: Additional useful scopes
    public function scopeExcellent(Builder $query): Builder
    {
        return $query->where('rating', self::MAX_RATING);
    }

    public function scopePoor(Builder $query): Builder
    {
        return $query->where('rating', self::MIN_RATING);
    }

    public function scopeNeutral(Builder $query): Builder
    {
        return $query->where('rating', self::NEUTRAL_RATING);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeHelpful(Builder $query, int $minHelpfulCount = 1): Builder
    {
        return $query->where('helpful_count', '>=', $minHelpfulCount);
    }

    public function scopeDetailed(Builder $query, int $minCommentLength = 50): Builder
    {
        return $query->whereNotNull('comment')
            ->whereRaw('LENGTH(comment) >= ?', [$minCommentLength]);
    }

    public function scopeRatingRange(Builder $query, int $min, int $max): Builder
    {
        return $query->whereBetween('rating', [$min, $max]);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS - ✅ IMPROVED with Laravel 9+ syntax and enhanced logic
    |--------------------------------------------------------------------------
    */

    protected function ratingStars(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function () {
                $filled = str_repeat('⭐', $this->rating);
                $empty = str_repeat('☆', self::MAX_RATING - $this->rating);

                return $filled . $empty;
            }
        );
    }

    protected function ratingColor(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => match ($this->rating) {
                5 => 'success',
                4 => 'info',
                3 => 'warning',
                2 => 'orange',
                1 => 'danger',
                default => 'gray'
            }
        );
    }

    protected function hasComment(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => ! empty(trim($this->comment))
        );
    }

    protected function ratingLevel(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => match ($this->rating) {
                5 => 'excellent',
                4 => 'good',
                3 => 'average',
                2 => 'poor',
                1 => 'terrible',
                default => 'unrated'
            }
        );
    }

    protected function commentLength(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => $this->comment ? strlen(trim($this->comment)) : 0
        );
    }

    protected function isPositive(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => $this->rating >= self::HIGH_RATING_THRESHOLD
        );
    }

    protected function isNegative(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => $this->rating <= self::LOW_RATING_THRESHOLD
        );
    }

    protected function timeAgo(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn() => $this->created_at?->diffForHumans() ?? 'Unknown'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC METHODS - ✅ IMPROVED with caching and comprehensive analytics
    |--------------------------------------------------------------------------
    */

    public static function updateStoryAggregate(int $storyId): void
    {
        try {
            $ratings = self::where('story_id', $storyId)->get();

            $totalRatings = $ratings->count();
            $sumRatings = $ratings->sum('rating');
            $averageRating = $totalRatings > 0 ? round($sumRatings / $totalRatings, 2) : 0;

            // Distribution calculation
            $distribution = [];
            for ($i = self::MIN_RATING; $i <= self::MAX_RATING; $i++) {
                $distribution[$i] = $ratings->where('rating', $i)->count();
            }

            // ✅ IMPROVED: Additional aggregate metrics
            $verifiedRatings = $ratings->where('is_verified', true);
            $ratingsWithComments = $ratings->filter(fn($r) => ! empty(trim($r->comment)));

            \App\Models\StoryRatingAggregate::updateOrCreate(
                ['story_id' => $storyId],
                [
                    'total_ratings' => $totalRatings,
                    'sum_ratings' => $sumRatings,
                    'average_rating' => $averageRating,
                    'rating_distribution' => $distribution,
                    'verified_ratings_count' => $verifiedRatings->count(),
                    'verified_average_rating' => $verifiedRatings->count() > 0
                        ? round($verifiedRatings->avg('rating'), 2)
                        : 0,
                    'comments_count' => $ratingsWithComments->count(),
                    'last_rated_at' => $ratings->max('created_at'),
                ]
            );

            // ✅ IMPROVED: Clear related caches
            cache()->forget("story_rating_stats_{$storyId}");
            cache()->forget("story_ratings_analysis_{$storyId}");
        } catch (\Exception $e) {
            Log::error('Failed to update story rating aggregate', [
                'story_id' => $storyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function getStoryRatingStats(int $storyId): array
    {
        return cache()->remember("story_rating_stats_{$storyId}", 600, function () use ($storyId) {
            $aggregate = \App\Models\StoryRatingAggregate::where('story_id', $storyId)->first();

            if (! $aggregate) {
                return [
                    'average_rating' => 0,
                    'total_ratings' => 0,
                    'rating_distribution' => array_fill_keys(self::VALID_RATINGS, 0),
                    'recent_ratings' => collect(),
                    'comments_count' => 0,
                    'verified_ratings_count' => 0,
                    'sentiment_analysis' => self::getDefaultSentimentAnalysis(),
                ];
            }

            $recentRatings = self::where('story_id', $storyId)
                ->with(['member:id,name'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            return [
                'average_rating' => $aggregate->average_rating,
                'total_ratings' => $aggregate->total_ratings,
                'verified_average_rating' => $aggregate->verified_average_rating ?? 0,
                'verified_ratings_count' => $aggregate->verified_ratings_count ?? 0,
                'rating_distribution' => $aggregate->rating_distribution,
                'recent_ratings' => $recentRatings,
                'comments_count' => $aggregate->comments_count ?? 0,
                'last_rated_at' => $aggregate->last_rated_at,
                'sentiment_analysis' => self::calculateSentimentAnalysis($storyId),
                'rating_trends' => self::getRatingTrends($storyId),
            ];
        });
    }

    public static function getMemberRating(int $memberId, int $storyId): ?self
    {
        return cache()->remember("member_rating_{$memberId}_{$storyId}", 300, function () use ($memberId, $storyId) {
            return self::where([
                'member_id' => $memberId,
                'story_id' => $storyId,
            ])->first();
        });
    }

    // ✅ NEW: Advanced analytics methods
    public static function calculateSentimentAnalysis(int $storyId): array
    {
        return cache()->remember("story_ratings_analysis_{$storyId}", 1800, function () use ($storyId) {
            $ratings = self::where('story_id', $storyId)->get();

            if ($ratings->isEmpty()) {
                return self::getDefaultSentimentAnalysis();
            }

            $total = $ratings->count();
            $positive = $ratings->where('rating', '>=', self::HIGH_RATING_THRESHOLD)->count();
            $negative = $ratings->where('rating', '<=', self::LOW_RATING_THRESHOLD)->count();
            $neutral = $total - $positive - $negative;

            return [
                'positive_percentage' => round(($positive / $total) * 100, 1),
                'negative_percentage' => round(($negative / $total) * 100, 1),
                'neutral_percentage' => round(($neutral / $total) * 100, 1),
                'sentiment_score' => round((($positive - $negative) / $total) * 100, 2),
                'recommendation_rate' => round(($positive / $total) * 100, 1),
            ];
        });
    }

    public static function getRatingTrends(int $storyId, int $days = 30): array
    {
        return cache()->remember("story_rating_trends_{$storyId}_{$days}", 1800, function () use ($storyId, $days) {
            return self::where('story_id', $storyId)
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw('DATE(created_at) as date, AVG(rating) as avg_rating, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'average_rating' => round($item->avg_rating, 2),
                        'ratings_count' => $item->count,
                    ];
                })
                ->toArray();
        });
    }

    public static function getTopRatedStories(int $limit = 10, int $minRatings = 5): Collection
    {
        return cache()->remember("top_rated_stories_{$limit}_{$minRatings}", 1800, function () use ($limit, $minRatings) {
            return \App\Models\StoryRatingAggregate::with(['story:id,title,slug'])
                ->where('total_ratings', '>=', $minRatings)
                ->orderByDesc('average_rating')
                ->orderByDesc('total_ratings')
                ->limit($limit)
                ->get();
        });
    }

    public static function getMostActiveRaters(int $limit = 10): Collection
    {
        return cache()->remember("most_active_raters_{$limit}", 1800, function () use ($limit) {
            return self::with(['member:id,name,email'])
                ->selectRaw('member_id, COUNT(*) as ratings_count, AVG(rating) as avg_rating, COUNT(CASE WHEN comment IS NOT NULL AND comment != "" THEN 1 END) as comments_count')
                ->groupBy('member_id')
                ->having('ratings_count', '>', 0)
                ->orderByDesc('ratings_count')
                ->limit($limit)
                ->get();
        });
    }

    public static function getRatingDistributionGlobal(): array
    {
        return cache()->remember('global_rating_distribution', 3600, function () {
            $distribution = [];
            for ($i = self::MIN_RATING; $i <= self::MAX_RATING; $i++) {
                $distribution[$i] = self::where('rating', $i)->count();
            }

            $total = array_sum($distribution);
            $percentages = [];
            foreach ($distribution as $rating => $count) {
                $percentages[$rating] = $total > 0 ? round(($count / $total) * 100, 1) : 0;
            }

            return [
                'counts' => $distribution,
                'percentages' => $percentages,
                'total_ratings' => $total,
                'average_rating' => $total > 0 ? round(self::avg('rating'), 2) : 0,
            ];
        });
    }

    private static function getDefaultSentimentAnalysis(): array
    {
        return [
            'positive_percentage' => 0,
            'negative_percentage' => 0,
            'neutral_percentage' => 0,
            'sentiment_score' => 0,
            'recommendation_rate' => 0,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | INSTANCE METHODS - ✅ IMPROVED with better functionality
    |--------------------------------------------------------------------------
    */

    public function markAsHelpful(): bool
    {
        try {
            return $this->increment('helpful_count');
        } catch (\Exception $e) {
            Log::error('Failed to mark rating as helpful', [
                'rating_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function markAsVerified(): bool
    {
        try {
            $result = $this->update(['is_verified' => true]);
            if ($result) {
                self::updateStoryAggregate($this->story_id);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to verify rating', [
                'rating_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function updateRating(int $newRating, ?string $newComment = null): bool
    {
        try {
            if (! in_array($newRating, self::VALID_RATINGS)) {
                throw new \InvalidArgumentException('Invalid rating value');
            }

            $updateData = ['rating' => $newRating];
            if ($newComment !== null) {
                $updateData['comment'] = trim($newComment);
            }

            $result = $this->update($updateData);
            if ($result) {
                self::updateStoryAggregate($this->story_id);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to update rating', [
                'rating_id' => $this->id,
                'new_rating' => $newRating,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MODEL EVENTS - ✅ IMPROVED with better performance and caching
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::saving(function ($rating) {
            // Validate rating value
            if (! in_array($rating->rating, self::VALID_RATINGS)) {
                throw new \InvalidArgumentException('Rating must be between ' . self::MIN_RATING . ' and ' . self::MAX_RATING);
            }

            // Clean comment
            if ($rating->comment) {
                $rating->comment = trim($rating->comment);
                if (empty($rating->comment)) {
                    $rating->comment = null;
                }
            }
        });

        static::saved(function ($rating) {
            // Update aggregates and clear caches
            self::updateStoryAggregate($rating->story_id);
            cache()->forget("member_rating_{$rating->member_id}_{$rating->story_id}");
        });

        static::deleted(function ($rating) {
            // Update aggregates and clear caches
            self::updateStoryAggregate($rating->story_id);
            cache()->forget("member_rating_{$rating->member_id}_{$rating->story_id}");
        });

        // ✅ NEW: Prevent duplicate ratings
        static::creating(function ($rating) {
            $existing = self::where('member_id', $rating->member_id)
                ->where('story_id', $rating->story_id)
                ->exists();

            if ($existing) {
                throw new \Exception('Member has already rated this story. Use update instead.');
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
            'rating' => 'required|integer|min:' . self::MIN_RATING . '|max:' . self::MAX_RATING,
            'comment' => 'nullable|string|max:1000|min:10',
            'is_verified' => 'boolean',
            'helpful_count' => 'integer|min:0',
        ];
    }

    public static function messages(): array
    {
        return [
            'member_id.exists' => 'The selected member does not exist.',
            'story_id.exists' => 'The selected story does not exist.',
            'rating.between' => 'Rating must be between ' . self::MIN_RATING . ' and ' . self::MAX_RATING . ' stars.',
            'comment.min' => 'Comment must be at least 10 characters long.',
            'comment.max' => 'Comment cannot exceed 1000 characters.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FILAMENT INTEGRATION - ✅ NEW for better admin interface
    |--------------------------------------------------------------------------
    */

    public function getFilamentName(): string
    {
        return $this->member?->name . ' → ' . $this->story?->title . ' (' . $this->rating . '⭐)';
    }

    public function getRatingBadgeColor(): string
    {
        return $this->rating_color;
    }

    // ✅ NEW: Bulk operations for admin efficiency
    public static function bulkVerifyRatings(array $ratingIds): int
    {
        try {
            $verified = self::whereIn('id', $ratingIds)
                ->update(['is_verified' => true]);

            // Update aggregates for affected stories
            $storyIds = self::whereIn('id', $ratingIds)
                ->distinct('story_id')
                ->pluck('story_id');

            foreach ($storyIds as $storyId) {
                self::updateStoryAggregate($storyId);
            }

            return $verified;
        } catch (\Exception $e) {
            Log::error('Failed to bulk verify ratings', [
                'rating_ids' => $ratingIds,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
