<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Member Story Rating Model - Enhanced with Filament Integration
 *
 * @property int $id
 * @property int $member_id
 * @property int $story_id
 * @property int $rating
 * @property string|null $comment
 * @property bool $is_verified
 * @property int $helpful_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Member $member
 * @property-read Story $story
 * @property-read StoryRatingAggregate $aggregate
 */
class MemberStoryRating extends Model
{
    use HasFactory;

    /**
     * Rating system constants for validation
     */
    public const MIN_RATING = 1;
    public const MAX_RATING = 5;
    public const VALID_RATINGS = [1, 2, 3, 4, 5];

    /**
     * Rating classification constants
     */
    public const HIGH_RATING_THRESHOLD = 4;
    public const LOW_RATING_THRESHOLD = 2;
    public const NEUTRAL_RATING = 3;

    /**
     * Cache TTL constants
     */
    private const CACHE_SHORT = 300; // 5 minutes
    private const CACHE_MEDIUM = 900; // 15 minutes
    private const CACHE_LONG = 3600; // 1 hour

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'member_id',
        'story_id',
        'rating',
        'comment',
        'is_verified',
        'helpful_count',
    ];

    /**
     * The attributes that should be cast.
     */
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
    | RELATIONSHIPS - Optimized with better performance
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
    | SCOPES - Comprehensive filtering options
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

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeUnverified(Builder $query): Builder
    {
        return $query->where('is_verified', false);
    }

    public function scopeHighRatings(Builder $query): Builder
    {
        return $query->where('rating', '>=', self::HIGH_RATING_THRESHOLD);
    }

    public function scopeLowRatings(Builder $query): Builder
    {
        return $query->where('rating', '<=', self::LOW_RATING_THRESHOLD);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopePopular(Builder $query, int $minHelpfulCount = 5): Builder
    {
        return $query->where('helpful_count', '>=', $minHelpfulCount);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS - Better data presentation
    |--------------------------------------------------------------------------
    */

    public function getRatingLabelAttribute(): string
    {
        $labels = [
            1 => 'Poor',
            2 => 'Fair',
            3 => 'Good',
            4 => 'Very Good',
            5 => 'Excellent',
        ];

        return $labels[$this->rating] ?? 'Unknown';
    }

    public function getRatingColorAttribute(): string
    {
        $colors = [
            1 => 'danger',
            2 => 'warning',
            3 => 'gray',
            4 => 'success',
            5 => 'success',
        ];

        return $colors[$this->rating] ?? 'gray';
    }

    public function getStarsDisplayAttribute(): string
    {
        return str_repeat('⭐', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    public function getIsHighRatingAttribute(): bool
    {
        return $this->rating >= self::HIGH_RATING_THRESHOLD;
    }

    public function getIsLowRatingAttribute(): bool
    {
        return $this->rating <= self::LOW_RATING_THRESHOLD;
    }

    public function getHasCommentAttribute(): bool
    {
        return !empty(trim($this->comment ?? ''));
    }

    public function getCommentExcerptAttribute(): ?string
    {
        if (!$this->has_comment) {
            return null;
        }

        return strlen($this->comment) > 100
            ? substr($this->comment, 0, 97) . '...'
            : $this->comment;
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC METHODS - Enhanced aggregate operations
    |--------------------------------------------------------------------------
    */

    public static function updateStoryAggregate(int $storyId): bool
    {
        try {
            $ratings = self::where('story_id', $storyId)->get();

            if ($ratings->isEmpty()) {
                // Delete aggregate if no ratings exist
                StoryRatingAggregate::where('story_id', $storyId)->delete();
                return true;
            }

            $totalRatings = $ratings->count();
            $sumRatings = $ratings->sum('rating');
            $averageRating = round($sumRatings / $totalRatings, 2);

            // Calculate distribution
            $distribution = [];
            for ($i = 1; $i <= 5; $i++) {
                $distribution[$i] = $ratings->where('rating', $i)->count();
            }

            // Calculate verified ratings
            $verifiedRatings = $ratings->where('is_verified', true);
            $verifiedCount = $verifiedRatings->count();
            $verifiedAverage = $verifiedCount > 0
                ? round($verifiedRatings->sum('rating') / $verifiedCount, 2)
                : 0;

            // Comments count
            $commentsCount = $ratings->whereNotNull('comment')
                ->where('comment', '!=', '')
                ->count();

            StoryRatingAggregate::updateOrCreate(
                ['story_id' => $storyId],
                [
                    'total_ratings' => $totalRatings,
                    'sum_ratings' => $sumRatings,
                    'average_rating' => $averageRating,
                    'rating_distribution' => $distribution,
                    'verified_ratings_count' => $verifiedCount,
                    'verified_average_rating' => $verifiedAverage,
                    'comments_count' => $commentsCount,
                    'last_rated_at' => $ratings->max('created_at'),
                ]
            );

            // Clear related caches
            Cache::forget("story_ratings_{$storyId}");
            Cache::forget("story_rating_stats_{$storyId}");

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update story rating aggregate', [
                'story_id' => $storyId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public static function getPopularRatings(int $limit = 10): Collection
    {
        return Cache::remember("popular_ratings_{$limit}", self::CACHE_MEDIUM, function () use ($limit) {
            return self::with(['member', 'story'])
                ->where('helpful_count', '>', 0)
                ->orderBy('helpful_count', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    public static function getRatingDistribution(int|null $storyId = null): array
    {
        $query = self::query();

        if ($storyId) {
            $query->where('story_id', $storyId);
        }

        return Cache::remember("rating_distribution_{$storyId}", self::CACHE_MEDIUM, function () use ($query) {
            $total = $query->count();
            $distribution = [];
            $percentages = [];

            for ($i = 1; $i <= 5; $i++) {
                $count = (clone $query)->where('rating', $i)->count();
                $distribution[$i] = $count;
                $percentages[$i] = $total > 0 ? round(($count / $total) * 100, 1) : 0;
            }

            return [
                'counts' => $distribution,
                'percentages' => $percentages,
                'total_ratings' => $total,
                'average_rating' => $total > 0 ? round($query->avg('rating'), 2) : 0,
            ];
        });
    }

    public static function getSentimentAnalysis(int|null $storyId = null): array
    {
        $query = self::query()->whereNotNull('comment');

        if ($storyId) {
            $query->where('story_id', $storyId);
        }

        return Cache::remember("rating_sentiment_{$storyId}", self::CACHE_LONG, function () use ($query) {
            $ratings = $query->get();

            if ($ratings->isEmpty()) {
                return self::getDefaultSentimentAnalysis();
            }

            $positive = $ratings->where('rating', '>=', 4)->count();
            $negative = $ratings->where('rating', '<=', 2)->count();
            $neutral = $ratings->where('rating', 3)->count();
            $total = $ratings->count();

            return [
                'positive_percentage' => $total > 0 ? round(($positive / $total) * 100, 1) : 0,
                'negative_percentage' => $total > 0 ? round(($negative / $total) * 100, 1) : 0,
                'neutral_percentage' => $total > 0 ? round(($neutral / $total) * 100, 1) : 0,
                'sentiment_score' => $total > 0 ? round($ratings->avg('rating'), 2) : 0,
                'recommendation_rate' => $total > 0 ? round(($positive / $total) * 100, 1) : 0,
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
    | INSTANCE METHODS - Enhanced functionality
    |--------------------------------------------------------------------------
    */

    public function markAsHelpful(): bool
    {
        try {
            return (bool) $this->increment('helpful_count');
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
                Cache::forget("member_rating_{$this->member_id}_{$this->story_id}");
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

    public function updateRating(int $newRating, string|null $newComment = null): bool
    {
        try {
            if (!in_array($newRating, self::VALID_RATINGS)) {
                throw new \InvalidArgumentException('Invalid rating value');
            }

            $updateData = ['rating' => $newRating];
            if ($newComment !== null) {
                $updateData['comment'] = trim($newComment);
                if (empty($updateData['comment'])) {
                    $updateData['comment'] = null;
                }
            }

            $result = $this->update($updateData);
            if ($result) {
                self::updateStoryAggregate($this->story_id);
                Cache::forget("member_rating_{$this->member_id}_{$this->story_id}");
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
    | MODEL EVENTS - Enhanced with better performance and caching
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::saving(function ($rating) {
            // Validate rating value
            if (!in_array($rating->rating, self::VALID_RATINGS)) {
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
            Cache::forget("member_rating_{$rating->member_id}_{$rating->story_id}");
        });

        static::deleted(function ($rating) {
            // Update aggregates and clear caches
            self::updateStoryAggregate($rating->story_id);
            Cache::forget("member_rating_{$rating->member_id}_{$rating->story_id}");
        });

        // Prevent duplicate ratings
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
    | VALIDATION - Comprehensive rules
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
    | FILAMENT INTEGRATION - Enhanced for better admin interface
    |--------------------------------------------------------------------------
    */

    public function getFilamentName(): string
    {
        return $this->member?->name . ' → ' . $this->story?->title . ' (' . $this->rating . '/5)';
    }

    public function getFilamentBadgeColor(): string
    {
        return $this->rating_color;
    }

    public function getFilamentDescription(): string
    {
        $parts = [];
        $parts[] = $this->stars_display;

        if ($this->is_verified) {
            $parts[] = '✅ Verified';
        }

        if ($this->helpful_count > 0) {
            $parts[] = "👍 {$this->helpful_count} helpful";
        }

        if ($this->has_comment) {
            $parts[] = '💬 Has comment';
        }

        return implode(' | ', $parts);
    }

    /**
     * Bulk operations for admin efficiency
     */
    public static function bulkVerifyRatings(array $ratingIds): int
    {
        try {
            $updated = self::whereIn('id', $ratingIds)
                ->update(['is_verified' => true]);

            // Update aggregates for affected stories
            $storyIds = self::whereIn('id', $ratingIds)
                ->distinct()
                ->pluck('story_id');

            foreach ($storyIds as $storyId) {
                self::updateStoryAggregate($storyId);
            }

            return $updated;
        } catch (\Exception $e) {
            Log::error('Failed to bulk verify ratings', [
                'rating_ids' => $ratingIds,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public static function bulkDeleteRatings(array $ratingIds): int
    {
        try {
            // Get story IDs before deletion
            $storyIds = self::whereIn('id', $ratingIds)
                ->distinct()
                ->pluck('story_id');

            $deleted = self::whereIn('id', $ratingIds)->delete();

            // Update aggregates for affected stories
            foreach ($storyIds as $storyId) {
                self::updateStoryAggregate($storyId);
            }

            return $deleted;
        } catch (\Exception $e) {
            Log::error('Failed to bulk delete ratings', [
                'rating_ids' => $ratingIds,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
