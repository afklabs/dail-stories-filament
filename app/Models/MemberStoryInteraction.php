<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class MemberStoryInteraction extends Model
{
    use HasFactory;

    // ✅ IMPROVED: Added validation-friendly constants
    public const ACTION_LIKE = 'like';
    public const ACTION_DISLIKE = 'dislike';
    public const ACTION_BOOKMARK = 'bookmark';
    public const ACTION_SHARE = 'share';
    public const ACTION_VIEW = 'view';
    public const ACTION_REPORT = 'report';

    public const VALID_ACTIONS = [
        self::ACTION_LIKE,
        self::ACTION_DISLIKE,
        self::ACTION_BOOKMARK,
        self::ACTION_SHARE,
        self::ACTION_VIEW,
        self::ACTION_REPORT,
    ];

    protected $fillable = [
        'member_id',
        'story_id',
        'action',
        'metadata', // ✅ NEW: For additional interaction data
    ];

    // ✅ IMPROVED: Enhanced casting with JSON support
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'member_id' => 'integer',
        'story_id' => 'integer',
        'metadata' => 'array', // ✅ NEW: For storing additional interaction data
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

    /*
    |--------------------------------------------------------------------------
    | SCOPES - ✅ IMPROVED with better naming and additional scopes
    |--------------------------------------------------------------------------
    */

    public function scopeLikes(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_LIKE);
    }

    public function scopeDislikes(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_DISLIKE);
    }

    public function scopeBookmarks(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_BOOKMARK);
    }

    public function scopeShares(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_SHARE);
    }

    public function scopeViews(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_VIEW);
    }

    public function scopeReports(Builder $query): Builder
    {
        return $query->where('action', self::ACTION_REPORT);
    }

    public function scopeByMember(Builder $query, int $memberId): Builder
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeByStory(Builder $query, int $storyId): Builder
    {
        return $query->where('story_id', $storyId);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ✅ NEW: Additional useful scopes
    public function scopePositive(Builder $query): Builder
    {
        return $query->whereIn('action', [self::ACTION_LIKE, self::ACTION_BOOKMARK, self::ACTION_SHARE]);
    }

    public function scopeNegative(Builder $query): Builder
    {
        return $query->whereIn('action', [self::ACTION_DISLIKE, self::ACTION_REPORT]);
    }

    public function scopeEngagement(Builder $query): Builder
    {
        return $query->whereIn('action', [self::ACTION_LIKE, self::ACTION_DISLIKE, self::ACTION_BOOKMARK, self::ACTION_SHARE]);
    }

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS - ✅ IMPROVED with Laravel 9+ syntax and better logic
    |--------------------------------------------------------------------------
    */

    protected function actionIcon(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => match ($this->action) {
                self::ACTION_LIKE => '👍',
                self::ACTION_DISLIKE => '👎',
                self::ACTION_BOOKMARK => '🔖',
                self::ACTION_SHARE => '📤',
                self::ACTION_VIEW => '👁️',
                self::ACTION_REPORT => '⚠️',
                default => '❓'
            }
        );
    }

    protected function actionColor(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => match ($this->action) {
                self::ACTION_LIKE => 'success',
                self::ACTION_DISLIKE => 'danger',
                self::ACTION_BOOKMARK => 'warning',
                self::ACTION_SHARE => 'info',
                self::ACTION_VIEW => 'primary',
                self::ACTION_REPORT => 'danger',
                default => 'gray'
            }
        );
    }

    protected function actionLabel(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => match ($this->action) {
                self::ACTION_LIKE => 'Liked',
                self::ACTION_DISLIKE => 'Disliked',
                self::ACTION_BOOKMARK => 'Bookmarked',
                self::ACTION_SHARE => 'Shared',
                self::ACTION_VIEW => 'Viewed',
                self::ACTION_REPORT => 'Reported',
                default => ucfirst($this->action)
            }
        );
    }

    protected function isPositive(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => in_array($this->action, [self::ACTION_LIKE, self::ACTION_BOOKMARK, self::ACTION_SHARE])
        );
    }

    protected function isNegative(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => in_array($this->action, [self::ACTION_DISLIKE, self::ACTION_REPORT])
        );
    }

    protected function timeAgo(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->created_at?->diffForHumans() ?? 'Unknown'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC METHODS - ✅ IMPROVED with caching and performance optimization
    |--------------------------------------------------------------------------
    */

    public static function getStoryStats(int $storyId): array
    {
        return cache()->remember("story_interaction_stats_{$storyId}", 300, function () use ($storyId) {
            $interactions = self::where('story_id', $storyId);

            return [
                'total_interactions' => $interactions->count(),
                'likes_count' => $interactions->clone()->likes()->count(),
                'dislikes_count' => $interactions->clone()->dislikes()->count(),
                'bookmarks_count' => $interactions->clone()->bookmarks()->count(),
                'shares_count' => $interactions->clone()->shares()->count(),
                'views_count' => $interactions->clone()->views()->count(),
                'reports_count' => $interactions->clone()->reports()->count(),
                'unique_members' => $interactions->clone()->distinct('member_id')->count(),
                'engagement_rate' => self::calculateEngagementRate($storyId),
                'sentiment_score' => self::calculateSentimentScore($storyId),
                'last_interaction' => $interactions->clone()->latest()->value('created_at'),
            ];
        });
    }

    public static function getMemberStats(int $memberId): array
    {
        return cache()->remember("member_interaction_stats_{$memberId}", 300, function () use ($memberId) {
            $interactions = self::where('member_id', $memberId);

            return [
                'total_interactions' => $interactions->count(),
                'likes_count' => $interactions->clone()->likes()->count(),
                'dislikes_count' => $interactions->clone()->dislikes()->count(),
                'bookmarks_count' => $interactions->clone()->bookmarks()->count(),
                'shares_count' => $interactions->clone()->shares()->count(),
                'views_count' => $interactions->clone()->views()->count(),
                'reports_count' => $interactions->clone()->reports()->count(),
                'unique_stories' => $interactions->clone()->distinct('story_id')->count(),
                'positive_interactions' => $interactions->clone()->positive()->count(),
                'negative_interactions' => $interactions->clone()->negative()->count(),
                'engagement_score' => self::calculateMemberEngagementScore($memberId),
                'last_interaction' => $interactions->clone()->latest()->value('created_at'),
            ];
        });
    }

    public static function getTrendingInteractions(int $days = 7, ?string $action = null): Collection
    {
        $cacheKey = "trending_interactions_{$days}" . ($action ? "_{$action}" : '');
        
        return cache()->remember($cacheKey, 300, function () use ($days, $action) {
            $query = self::with(['story:id,title,slug', 'member:id,name'])
                ->where('created_at', '>=', now()->subDays($days));

            if ($action && in_array($action, self::VALID_ACTIONS)) {
                $query->where('action', $action);
            }

            return $query->orderByDesc('created_at')
                ->limit(100) // ✅ IMPROVED: Limit results for performance
                ->get();
        });
    }

    // ✅ NEW: Advanced analytics methods
    public static function getInteractionTrends(int $days = 30): array
    {
        return cache()->remember("interaction_trends_{$days}", 600, function () use ($days) {
            return self::selectRaw('DATE(created_at) as date, action, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy(['date', 'action'])
                ->orderBy('date')
                ->get()
                ->groupBy('date')
                ->map(function ($dayInteractions) {
                    $result = ['date' => $dayInteractions->first()->date];
                    foreach (self::VALID_ACTIONS as $action) {
                        $result[$action] = $dayInteractions->where('action', $action)->sum('count');
                    }
                    $result['total'] = $dayInteractions->sum('count');
                    return $result;
                })
                ->values()
                ->toArray();
        });
    }

    public static function getMostEngagedStories(int $limit = 10): Collection
    {
        return cache()->remember("most_engaged_stories_{$limit}", 600, function () use ($limit) {
            return self::with(['story:id,title,slug'])
                ->selectRaw('story_id, COUNT(*) as total_interactions, 
                    SUM(CASE WHEN action IN ("like", "bookmark", "share") THEN 1 ELSE 0 END) as positive_interactions,
                    COUNT(DISTINCT member_id) as unique_members')
                ->groupBy('story_id')
                ->having('total_interactions', '>', 0)
                ->orderByDesc('total_interactions')
                ->limit($limit)
                ->get();
        });
    }

    public static function getMostActiveMembers(int $limit = 10): Collection
    {
        return cache()->remember("most_active_members_{$limit}", 600, function () use ($limit) {
            return self::with(['member:id,name,email'])
                ->selectRaw('member_id, COUNT(*) as total_interactions,
                    COUNT(DISTINCT story_id) as unique_stories,
                    SUM(CASE WHEN action IN ("like", "bookmark", "share") THEN 1 ELSE 0 END) as positive_interactions')
                ->groupBy('member_id')
                ->having('total_interactions', '>', 0)
                ->orderByDesc('total_interactions')
                ->limit($limit)
                ->get();
        });
    }

    // ✅ NEW: Sentiment and engagement calculations
    private static function calculateSentimentScore(int $storyId): float
    {
        $interactions = self::where('story_id', $storyId);
        $positive = $interactions->clone()->positive()->count();
        $negative = $interactions->clone()->negative()->count();
        $total = $positive + $negative;

        if ($total === 0) return 0;

        return round((($positive - $negative) / $total) * 100, 2);
    }

    private static function calculateEngagementRate(int $storyId): float
    {
        $views = self::where('story_id', $storyId)->views()->count();
        $engagements = self::where('story_id', $storyId)->engagement()->count();

        if ($views === 0) return 0;

        return round(($engagements / $views) * 100, 2);
    }

    private static function calculateMemberEngagementScore(int $memberId): float
    {
        $interactions = self::where('member_id', $memberId);
        $total = $interactions->count();
        $positive = $interactions->clone()->positive()->count();
        $unique_stories = $interactions->clone()->distinct('story_id')->count();

        if ($total === 0) return 0;

        // Score based on diversity and positivity
        $diversity_score = min($unique_stories / 10, 1); // Max score at 10+ stories
        $positivity_score = $positive / $total;

        return round(($diversity_score * 0.3 + $positivity_score * 0.7) * 100, 2);
    }

    /*
    |--------------------------------------------------------------------------
    | INSTANCE METHODS - ✅ IMPROVED with better validation
    |--------------------------------------------------------------------------
    */

    public function toggleAction(): bool
    {
        try {
            $newAction = match ($this->action) {
                self::ACTION_LIKE => self::ACTION_DISLIKE,
                self::ACTION_DISLIKE => self::ACTION_LIKE,
                default => $this->action
            };

            if ($newAction !== $this->action) {
                return $this->update(['action' => $newAction]);
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to toggle interaction action', [
                'interaction_id' => $this->id,
                'current_action' => $this->action,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function addMetadata(array $data): bool
    {
        try {
            $metadata = $this->metadata ?? [];
            $metadata = array_merge($metadata, $data);
            
            return $this->update(['metadata' => $metadata]);
        } catch (\Exception $e) {
            \Log::error('Failed to add interaction metadata', [
                'interaction_id' => $this->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MODEL EVENTS - ✅ IMPROVED with better performance and caching
    |--------------------------------------------------------------------------
    */

    protected static function boot(): void
    {
        parent::boot();

        // ✅ IMPROVED: Cache invalidation on changes
        static::saved(function ($model) {
            // Clear related caches
            cache()->forget("story_interaction_stats_{$model->story_id}");
            cache()->forget("member_interaction_stats_{$model->member_id}");
            
            // Clear trending caches
            cache()->forget("trending_interactions_7");
            cache()->forget("most_engaged_stories_10");
            cache()->forget("most_active_members_10");
        });

        static::deleted(function ($model) {
            // Clear related caches on deletion
            cache()->forget("story_interaction_stats_{$model->story_id}");
            cache()->forget("member_interaction_stats_{$model->member_id}");
        });

        // ✅ NEW: Prevent duplicate interactions
        static::creating(function ($model) {
            // Check for existing interaction
            $existing = self::where('member_id', $model->member_id)
                ->where('story_id', $model->story_id)
                ->where('action', $model->action)
                ->exists();

            if ($existing) {
                throw new \Exception("Interaction already exists for this member, story, and action.");
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
            'action' => 'required|string|in:' . implode(',', self::VALID_ACTIONS),
            'metadata' => 'nullable|array',
        ];
    }

    public static function messages(): array
    {
        return [
            'member_id.exists' => 'The selected member does not exist.',
            'story_id.exists' => 'The selected story does not exist.',
            'action.in' => 'The action must be one of: ' . implode(', ', self::VALID_ACTIONS),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FILAMENT INTEGRATION - ✅ NEW for better admin interface
    |--------------------------------------------------------------------------
    */

    public function getFilamentName(): string
    {
        return $this->member?->name . ' → ' . $this->story?->title . ' (' . $this->action_label . ')';
    }

    public function getActionBadgeColor(): string
    {
        return $this->action_color;
    }

    // ✅ NEW: Bulk operations for admin efficiency
    public static function bulkCreateInteractions(array $interactions): int
    {
        $created = 0;
        $errors = [];

        foreach ($interactions as $interactionData) {
            try {
                self::create($interactionData);
                $created++;
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            \Log::warning('Some bulk interactions failed', ['errors' => $errors]);
        }

        return $created;
    }
}