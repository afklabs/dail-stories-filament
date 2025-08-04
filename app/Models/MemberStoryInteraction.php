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
 * Member Story Interaction Model - Enhanced with Filament Integration
 *
 * @property int $id
 * @property int $member_id
 * @property int $story_id
 * @property string $action
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Member $member
 * @property-read Story $story
 * @property int $interaction_count
 * @property string|null $last_interacted_at
 * @property-read string $action_color
 * @property-read string $action_icon
 * @property-read string $action_label
 * @property-read bool $is_negative
 * @property-read bool $is_neutral
 * @property-read bool $is_positive
 * @property-read string|null $metadata_value
 * @method static Builder<static>|MemberStoryInteraction byAction(string $action)
 * @method static Builder<static>|MemberStoryInteraction byActions(array $actions)
 * @method static Builder<static>|MemberStoryInteraction byMember(int $memberId)
 * @method static Builder<static>|MemberStoryInteraction byStory(int $storyId)
 * @method static Builder<static>|MemberStoryInteraction negative()
 * @method static Builder<static>|MemberStoryInteraction neutral()
 * @method static Builder<static>|MemberStoryInteraction newModelQuery()
 * @method static Builder<static>|MemberStoryInteraction newQuery()
 * @method static Builder<static>|MemberStoryInteraction positive()
 * @method static Builder<static>|MemberStoryInteraction query()
 * @method static Builder<static>|MemberStoryInteraction recent(int $days = 7)
 * @method static Builder<static>|MemberStoryInteraction thisMonth()
 * @method static Builder<static>|MemberStoryInteraction thisWeek()
 * @method static Builder<static>|MemberStoryInteraction today()
 * @method static Builder<static>|MemberStoryInteraction whereAction($value)
 * @method static Builder<static>|MemberStoryInteraction whereCreatedAt($value)
 * @method static Builder<static>|MemberStoryInteraction whereId($value)
 * @method static Builder<static>|MemberStoryInteraction whereInteractionCount($value)
 * @method static Builder<static>|MemberStoryInteraction whereLastInteractedAt($value)
 * @method static Builder<static>|MemberStoryInteraction whereMemberId($value)
 * @method static Builder<static>|MemberStoryInteraction whereMetadata($value)
 * @method static Builder<static>|MemberStoryInteraction whereStoryId($value)
 * @method static Builder<static>|MemberStoryInteraction whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class MemberStoryInteraction extends Model
{
    use HasFactory;

    /**
     * Action constants for validation and consistency
     */
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

    /**
     * Action grouping for analytics
     */
    public const POSITIVE_ACTIONS = [self::ACTION_LIKE, self::ACTION_BOOKMARK, self::ACTION_SHARE];
    public const NEGATIVE_ACTIONS = [self::ACTION_DISLIKE, self::ACTION_REPORT];
    public const NEUTRAL_ACTIONS = [self::ACTION_VIEW];

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
        'action',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'member_id' => 'integer',
        'story_id' => 'integer',
        'metadata' => 'array',
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

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeByActions(Builder $query, array $actions): Builder
    {
        return $query->whereIn('action', $actions);
    }

    public function scopePositive(Builder $query): Builder
    {
        return $query->whereIn('action', self::POSITIVE_ACTIONS);
    }

    public function scopeNegative(Builder $query): Builder
    {
        return $query->whereIn('action', self::NEGATIVE_ACTIONS);
    }

    public function scopeNeutral(Builder $query): Builder
    {
        return $query->whereIn('action', self::NEUTRAL_ACTIONS);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS - Better data presentation
    |--------------------------------------------------------------------------
    */

    public function getActionLabelAttribute(): string
    {
        $labels = [
            self::ACTION_LIKE => 'Liked',
            self::ACTION_DISLIKE => 'Disliked',
            self::ACTION_BOOKMARK => 'Bookmarked',
            self::ACTION_SHARE => 'Shared',
            self::ACTION_VIEW => 'Viewed',
            self::ACTION_REPORT => 'Reported',
        ];

        return $labels[$this->action] ?? ucfirst($this->action);
    }

    public function getActionColorAttribute(): string
    {
        $colors = [
            self::ACTION_LIKE => 'success',
            self::ACTION_DISLIKE => 'danger',
            self::ACTION_BOOKMARK => 'warning',
            self::ACTION_SHARE => 'info',
            self::ACTION_VIEW => 'gray',
            self::ACTION_REPORT => 'danger',
        ];

        return $colors[$this->action] ?? 'gray';
    }

    public function getActionIconAttribute(): string
    {
        $icons = [
            self::ACTION_LIKE => '👍',
            self::ACTION_DISLIKE => '👎',
            self::ACTION_BOOKMARK => '🔖',
            self::ACTION_SHARE => '📤',
            self::ACTION_VIEW => '👁️',
            self::ACTION_REPORT => '🚩',
        ];

        return $icons[$this->action] ?? '📝';
    }

    public function getIsPositiveAttribute(): bool
    {
        return in_array($this->action, self::POSITIVE_ACTIONS);
    }

    public function getIsNegativeAttribute(): bool
    {
        return in_array($this->action, self::NEGATIVE_ACTIONS);
    }

    public function getIsNeutralAttribute(): bool
    {
        return in_array($this->action, self::NEUTRAL_ACTIONS);
    }

    public function getMetadataValueAttribute(): ?string
    {
        if (!$this->metadata) {
            return null;
        }

        $metadata = $this->metadata;
        $values = [];

        if (isset($metadata['device'])) {
            $values[] = "Device: {$metadata['device']}";
        }

        if (isset($metadata['platform'])) {
            $values[] = "Platform: {$metadata['platform']}";
        }

        if (isset($metadata['duration'])) {
            $values[] = "Duration: {$metadata['duration']}s";
        }

        if (isset($metadata['share_method'])) {
            $values[] = "Method: {$metadata['share_method']}";
        }

        return implode(' | ', $values);
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC METHODS - Enhanced analytics and operations
    |--------------------------------------------------------------------------
    */

    public static function getInteractionStats(int|null $storyId = null, int|null $memberId = null): array
    {
        $cacheKey = "interaction_stats_{$storyId}_{$memberId}";

        return Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($storyId, $memberId) {
            $query = self::query();

            if ($storyId) {
                $query->where('story_id', $storyId);
            }

            if ($memberId) {
                $query->where('member_id', $memberId);
            }

            $total = $query->count();
            $stats = [];

            foreach (self::VALID_ACTIONS as $action) {
                $count = (clone $query)->where('action', $action)->count();
                $stats[$action] = [
                    'count' => $count,
                    'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                ];
            }

            // Calculate engagement metrics
            $positiveCount = (clone $query)->whereIn('action', self::POSITIVE_ACTIONS)->count();
            $negativeCount = (clone $query)->whereIn('action', self::NEGATIVE_ACTIONS)->count();

            $stats['summary'] = [
                'total_interactions' => $total,
                'positive_interactions' => $positiveCount,
                'negative_interactions' => $negativeCount,
                'engagement_score' => $total > 0 ? round((($positiveCount - $negativeCount) / $total) * 100, 1) : 0,
                'positive_ratio' => $total > 0 ? round(($positiveCount / $total) * 100, 1) : 0,
            ];

            return $stats;
        });
    }

    public static function getTrendingInteractions(int $days = 7, int $limit = 10): Collection
    {
        return Cache::remember("trending_interactions_{$days}_{$limit}", self::CACHE_MEDIUM, function () use ($days, $limit) {
            return self::with(['story', 'member'])
                ->select('story_id', DB::raw('COUNT(*) as interaction_count'))
                ->where('created_at', '>=', now()->subDays($days))
                ->whereIn('action', self::POSITIVE_ACTIONS)
                ->groupBy('story_id')
                ->orderBy('interaction_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    public static function getMostEngagedStories(int $limit = 10): Collection
    {
        return Cache::remember("most_engaged_stories_{$limit}", self::CACHE_LONG, function () use ($limit) {
            return self::with(['story'])
                ->select('story_id', DB::raw('COUNT(DISTINCT member_id) as unique_members'), DB::raw('COUNT(*) as total_interactions'))
                ->groupBy('story_id')
                ->orderBy('unique_members', 'desc')
                ->orderBy('total_interactions', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    public static function getMostActiveMembers(int $limit = 10): Collection
    {
        return Cache::remember("most_active_members_{$limit}", self::CACHE_LONG, function () use ($limit) {
            return self::with(['member'])
                ->select('member_id', DB::raw('COUNT(DISTINCT story_id) as unique_stories'), DB::raw('COUNT(*) as total_interactions'))
                ->groupBy('member_id')
                ->orderBy('unique_stories', 'desc')
                ->orderBy('total_interactions', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    public static function getEngagementAnalytics(int $days = 30): array
    {
        return Cache::remember("engagement_analytics_{$days}", self::CACHE_LONG, function () use ($days) {
            $interactions = self::where('created_at', '>=', now()->subDays($days))->get();

            if ($interactions->isEmpty()) {
                return self::getDefaultEngagementAnalytics();
            }

            $totalInteractions = $interactions->count();
            $uniqueMembers = $interactions->pluck('member_id')->unique()->count();
            $uniqueStories = $interactions->pluck('story_id')->unique()->count();

            // Daily breakdown
            $dailyStats = [];
            for ($i = 0; $i < $days; $i++) {
                $date = now()->subDays($i)->format('Y-m-d');
                $dayInteractions = $interactions->where('created_at', '>=', $date . ' 00:00:00')
                    ->where('created_at', '<=', $date . ' 23:59:59');

                $dailyStats[$date] = [
                    'total' => $dayInteractions->count(),
                    'unique_members' => $dayInteractions->pluck('member_id')->unique()->count(),
                    'positive' => $dayInteractions->whereIn('action', self::POSITIVE_ACTIONS)->count(),
                    'negative' => $dayInteractions->whereIn('action', self::NEGATIVE_ACTIONS)->count(),
                ];
            }

            return [
                'period_days' => $days,
                'total_interactions' => $totalInteractions,
                'unique_members' => $uniqueMembers,
                'unique_stories' => $uniqueStories,
                'avg_interactions_per_member' => $uniqueMembers > 0 ? round($totalInteractions / $uniqueMembers, 1) : 0,
                'avg_interactions_per_story' => $uniqueStories > 0 ? round($totalInteractions / $uniqueStories, 1) : 0,
                'daily_stats' => array_reverse($dailyStats, true),
                'action_breakdown' => self::getInteractionStats(),
            ];
        });
    }

    private static function getDefaultEngagementAnalytics(): array
    {
        return [
            'period_days' => 0,
            'total_interactions' => 0,
            'unique_members' => 0,
            'unique_stories' => 0,
            'avg_interactions_per_member' => 0,
            'avg_interactions_per_story' => 0,
            'daily_stats' => [],
            'action_breakdown' => [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | INSTANCE METHODS - Enhanced functionality
    |--------------------------------------------------------------------------
    */

    public function addMetadata(array $data): bool
    {
        try {
            $metadata = $this->metadata ?? [];
            $metadata = array_merge($metadata, $data);

            return $this->update(['metadata' => $metadata]);
        } catch (\Exception $e) {
            Log::error('Failed to add interaction metadata', [
                'interaction_id' => $this->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function updateMetadata(array $data): bool
    {
        try {
            return $this->update(['metadata' => $data]);
        } catch (\Exception $e) {
            Log::error('Failed to update interaction metadata', [
                'interaction_id' => $this->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function hasMetadataKey(string $key): bool
    {
        return isset($this->metadata[$key]);
    }

    /*
    |--------------------------------------------------------------------------
    | MODEL EVENTS - Enhanced with better performance and caching
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::saving(function ($model) {
            // Validate action
            if (!in_array($model->action, self::VALID_ACTIONS)) {
                throw new \InvalidArgumentException('Invalid action: ' . $model->action);
            }

            // Clean metadata
            if ($model->metadata && empty(array_filter($model->metadata))) {
                $model->metadata = null;
            }
        });

        static::saved(function ($model) {
            // Clear related caches
            Cache::forget("story_interaction_stats_{$model->story_id}");
            Cache::forget("member_interaction_stats_{$model->member_id}");
            Cache::forget("interaction_stats_{$model->story_id}_{$model->member_id}");

            // Clear trending caches
            Cache::forget('trending_interactions_7');
            Cache::forget('most_engaged_stories_10');
            Cache::forget('most_active_members_10');
            Cache::forget('engagement_analytics_30');
        });

        static::deleted(function ($model) {
            // Clear related caches on deletion
            Cache::forget("story_interaction_stats_{$model->story_id}");
            Cache::forget("member_interaction_stats_{$model->member_id}");
            Cache::forget("interaction_stats_{$model->story_id}_{$model->member_id}");
        });

        // Prevent duplicate interactions based on configuration
        static::creating(function ($model) {
            // Allow multiple views but prevent duplicate non-view interactions
            if ($model->action !== self::ACTION_VIEW) {
                $existing = self::where('member_id', $model->member_id)
                    ->where('story_id', $model->story_id)
                    ->where('action', $model->action)
                    ->exists();

                if ($existing) {
                    throw new \Exception("Interaction already exists for this member, story, and action: {$model->action}");
                }
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
            'action' => 'required|string|in:' . implode(',', self::VALID_ACTIONS),
            'metadata' => 'nullable|array',
            'metadata.device' => 'nullable|string|max:100',
            'metadata.platform' => 'nullable|string|max:50',
            'metadata.version' => 'nullable|string|max:20',
            'metadata.duration' => 'nullable|integer|min:0',
            'metadata.share_method' => 'nullable|string|max:50',
        ];
    }

    public static function messages(): array
    {
        return [
            'member_id.exists' => 'The selected member does not exist.',
            'story_id.exists' => 'The selected story does not exist.',
            'action.in' => 'The action must be one of: ' . implode(', ', self::VALID_ACTIONS),
            'metadata.device.max' => 'Device name cannot exceed 100 characters.',
            'metadata.platform.max' => 'Platform name cannot exceed 50 characters.',
            'metadata.duration.min' => 'Duration must be a positive number.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FILAMENT INTEGRATION - Enhanced for better admin interface
    |--------------------------------------------------------------------------
    */

    public function getFilamentName(): string
    {
        return $this->member?->name . ' → ' . $this->story?->title . ' (' . $this->action_label . ')';
    }

    public function getFilamentBadgeColor(): string
    {
        return $this->action_color;
    }

    public function getFilamentDescription(): string
    {
        $parts = [];
        $parts[] = $this->action_icon . ' ' . $this->action_label;

        if ($this->metadata_value) {
            $parts[] = $this->metadata_value;
        }

        $parts[] = $this->created_at->format('M j, Y g:i A');

        return implode(' | ', $parts);
    }

    /**
     * Bulk operations for admin efficiency
     */
    public static function bulkCreateInteractions(array $interactions): int
    {
        $created = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($interactions as $interactionData) {
                try {
                    self::create($interactionData);
                    $created++;
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            if (count($errors) > count($interactions) / 2) {
                // If more than half failed, rollback
                DB::rollback();
                Log::error('Bulk interaction creation failed - too many errors', ['errors' => $errors]);
                return 0;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Bulk interaction creation failed', ['error' => $e->getMessage()]);
            return 0;
        }

        if (!empty($errors)) {
            Log::warning('Some bulk interactions failed', ['errors' => $errors]);
        }

        return $created;
    }

    public static function bulkDeleteInteractions(array $interactionIds): int
    {
        try {
            return self::whereIn('id', $interactionIds)->delete();
        } catch (\Exception $e) {
            Log::error('Failed to bulk delete interactions', [
                'interaction_ids' => $interactionIds,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get interaction summary for dashboard
     */
    public static function getDashboardSummary(): array
    {
        return Cache::remember('interaction_dashboard_summary', self::CACHE_SHORT, function () {
            $today = self::whereDate('created_at', today())->count();
            $yesterday = self::whereDate('created_at', today()->subDay())->count();
            $thisWeek = self::thisWeek()->count();
            $thisMonth = self::thisMonth()->count();

            $todayChange = $yesterday > 0 ? round((($today - $yesterday) / $yesterday) * 100, 1) : 0;

            return [
                'today' => $today,
                'yesterday' => $yesterday,
                'this_week' => $thisWeek,
                'this_month' => $thisMonth,
                'today_change_percent' => $todayChange,
                'trending_actions' => self::select('action', DB::raw('COUNT(*) as count'))
                    ->recent(7)
                    ->groupBy('action')
                    ->orderBy('count', 'desc')
                    ->limit(3)
                    ->pluck('count', 'action')
                    ->toArray(),
            ];
        });
    }


    public static function today(): Builder
    {
        return static::query()->whereDate('created_at', today());
    }

    public static function thisWeek(): Builder
    {
        return static::query()->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }
}
