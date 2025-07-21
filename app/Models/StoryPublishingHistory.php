<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * StoryPublishingHistory Model for Daily Stories App with Filament Integration
 *
 * Enhanced audit trail system for tracking story publishing activities
 * with comprehensive analytics and monitoring capabilities.
 *
 * @property int $id
 * @property int $story_id
 * @property int $user_id
 * @property string $action
 * @property bool|null $previous_active_status
 * @property bool|null $new_active_status
 * @property Carbon|null $previous_active_from
 * @property Carbon|null $previous_active_until
 * @property Carbon|null $new_active_from
 * @property Carbon|null $new_active_until
 * @property string|null $notes
 * @property array|null $changed_fields
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class StoryPublishingHistory extends Model
{
    use HasFactory;

    /**
     * Action type constants
     */
    public const ACTION_PUBLISHED = 'published';
    public const ACTION_UNPUBLISHED = 'unpublished';
    public const ACTION_REPUBLISHED = 'republished';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_SCHEDULED = 'scheduled';
    public const ACTION_EXPIRED = 'expired';
    public const ACTION_RESCHEDULED = 'rescheduled';
    public const ACTION_EXTENDED = 'extended';

    /**
     * Valid actions array
     */
    public const VALID_ACTIONS = [
        self::ACTION_PUBLISHED,
        self::ACTION_UNPUBLISHED,
        self::ACTION_REPUBLISHED,
        self::ACTION_UPDATED,
        self::ACTION_SCHEDULED,
        self::ACTION_EXPIRED,
        self::ACTION_RESCHEDULED,
        self::ACTION_EXTENDED,
    ];

    /**
     * Cache constants
     */
    private const CACHE_TTL = 600; // 10 minutes
    private const CACHE_TTL_ANALYTICS = 1800; // 30 minutes

    /**
     * The table associated with the model.
     */
    protected $table = 'story_publishing_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'story_id',
        'user_id',
        'action',
        'previous_active_status',
        'new_active_status',
        'previous_active_from',
        'previous_active_until',
        'new_active_from',
        'new_active_until',
        'notes',
        'changed_fields',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'previous_active_status' => 'boolean',
        'new_active_status' => 'boolean',
        'previous_active_from' => 'datetime',
        'previous_active_until' => 'datetime',
        'new_active_from' => 'datetime',
        'new_active_until' => 'datetime',
        'changed_fields' => 'array',
    ];

    /**
     * Model boot method for event handling
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-fill user agent and IP if not provided
        static::creating(function (self $history): void {
            if (empty($history->ip_address) && request()) {
                $history->ip_address = request()->ip();
            }
            
            if (empty($history->user_agent) && request()) {
                $history->user_agent = request()->header('User-Agent');
            }
        });

        // Clear analytics cache when new history is created
        static::created(function (self $history): void {
            $history->clearAnalyticsCache();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the story that this history belongs to.
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * Get the admin user who performed this action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Get formatted action name for display
     */
    public function getFormattedActionAttribute(): string
    {
        $actions = [
            self::ACTION_PUBLISHED => 'Published',
            self::ACTION_UNPUBLISHED => 'Unpublished',
            self::ACTION_REPUBLISHED => 'Republished',
            self::ACTION_UPDATED => 'Updated',
            self::ACTION_SCHEDULED => 'Scheduled',
            self::ACTION_EXPIRED => 'Expired',
            self::ACTION_RESCHEDULED => 'Rescheduled',
            self::ACTION_EXTENDED => 'Extended',
        ];

        return $actions[$this->action] ?? ucfirst($this->action);
    }

    /**
     * Get action color for UI badges
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_PUBLISHED, self::ACTION_REPUBLISHED => 'success',
            self::ACTION_UNPUBLISHED => 'warning',
            self::ACTION_UPDATED, self::ACTION_RESCHEDULED => 'info',
            self::ACTION_SCHEDULED => 'primary',
            self::ACTION_EXTENDED => 'secondary',
            self::ACTION_EXPIRED => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get action icon for UI display
     */
    public function getActionIconAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_PUBLISHED => 'check-circle',
            self::ACTION_UNPUBLISHED => 'pause-circle',
            self::ACTION_REPUBLISHED => 'refresh-cw',
            self::ACTION_UPDATED => 'edit',
            self::ACTION_SCHEDULED => 'clock',
            self::ACTION_EXPIRED => 'x-circle',
            self::ACTION_RESCHEDULED => 'calendar',
            self::ACTION_EXTENDED => 'arrow-right',
            default => 'activity',
        };
    }

    /**
     * Get changes summary for display
     */
    public function getChangesSummaryAttribute(): string
    {
        if (!$this->changed_fields || empty($this->changed_fields)) {
            return 'No specific changes recorded';
        }

        $fields = is_array($this->changed_fields) ? $this->changed_fields : [];
        $formattedFields = array_map(function ($field) {
            return ucfirst(str_replace('_', ' ', $field));
        }, $fields);

        return 'Changed: ' . implode(', ', $formattedFields);
    }

    /**
     * Get status change description
     */
    public function getStatusChangeAttribute(): string
    {
        if ($this->previous_active_status === $this->new_active_status) {
            return $this->new_active_status ? 'Remained Active' : 'Remained Inactive';
        }

        if ($this->previous_active_status === null) {
            return $this->new_active_status ? 'Set to Active' : 'Set to Inactive';
        }

        return $this->new_active_status ? 'Activated' : 'Deactivated';
    }

    /**
     * Get schedule change description
     */
    public function getScheduleChangeAttribute(): string
    {
        $changes = [];

        // Check active_from changes
        if ($this->previous_active_from !== $this->new_active_from) {
            if ($this->new_active_from) {
                $changes[] = 'Start: ' . $this->new_active_from->format('M j, Y H:i');
            } else {
                $changes[] = 'Start: Removed';
            }
        }

        // Check active_until changes
        if ($this->previous_active_until !== $this->new_active_until) {
            if ($this->new_active_until) {
                $changes[] = 'End: ' . $this->new_active_until->format('M j, Y H:i');
            } else {
                $changes[] = 'End: Removed';
            }
        }

        return empty($changes) ? 'No schedule changes' : implode(', ', $changes);
    }

    /**
     * Get action impact level
     */
    public function getImpactLevelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_PUBLISHED, self::ACTION_UNPUBLISHED => 'high',
            self::ACTION_SCHEDULED, self::ACTION_EXPIRED => 'medium',
            self::ACTION_UPDATED, self::ACTION_EXTENDED, self::ACTION_RESCHEDULED => 'low',
            default => 'minimal',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for recent operations
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope by action type
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope by admin user
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope by story
     */
    public function scopeByStory(Builder $query, int $storyId): Builder
    {
        return $query->where('story_id', $storyId);
    }

    /**
     * Scope for publishing actions (published, republished)
     */
    public function scopePublishingActions(Builder $query): Builder
    {
        return $query->whereIn('action', [
            self::ACTION_PUBLISHED,
            self::ACTION_REPUBLISHED,
        ]);
    }

    /**
     * Scope for scheduling actions
     */
    public function scopeSchedulingActions(Builder $query): Builder
    {
        return $query->whereIn('action', [
            self::ACTION_SCHEDULED,
            self::ACTION_RESCHEDULED,
            self::ACTION_EXTENDED,
        ]);
    }

    /**
     * Scope for high impact actions
     */
    public function scopeHighImpact(Builder $query): Builder
    {
        return $query->whereIn('action', [
            self::ACTION_PUBLISHED,
            self::ACTION_UNPUBLISHED,
            self::ACTION_EXPIRED,
        ]);
    }

    /**
     * Scope for today's activities
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    /**
     * Scope for this week's activities
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ANALYTICS METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Get comprehensive publishing analytics
     */
    public static function getPublishingAnalytics(int $days = 30): array
    {
        return Cache::remember('publishing_analytics_' . $days, self::CACHE_TTL_ANALYTICS, function () use ($days): array {
            $startDate = Carbon::now()->subDays($days);
            
            return [
                'activity_summary' => self::getActivitySummary($startDate),
                'action_breakdown' => self::getActionBreakdown($startDate),
                'daily_activity' => self::getDailyActivity($days),
                'user_activity' => self::getUserActivity($startDate),
                'story_activity' => self::getStoryActivity($startDate),
                'impact_analysis' => self::getImpactAnalysis($startDate),
            ];
        });
    }

    /**
     * Get activity summary
     */
    private static function getActivitySummary(Carbon $startDate): array
    {
        $totalActions = self::where('created_at', '>=', $startDate)->count();
        $todayActions = self::whereDate('created_at', Carbon::today())->count();
        $publishedToday = self::whereDate('created_at', Carbon::today())
            ->where('action', self::ACTION_PUBLISHED)->count();
        $scheduledToday = self::whereDate('created_at', Carbon::today())
            ->where('action', self::ACTION_SCHEDULED)->count();

        return [
            'total_actions' => $totalActions,
            'today_actions' => $todayActions,
            'published_today' => $publishedToday,
            'scheduled_today' => $scheduledToday,
            'average_daily' => round($totalActions / max(1, $startDate->diffInDays(Carbon::now())), 1),
        ];
    }

    /**
     * Get action breakdown
     */
    private static function getActionBreakdown(Carbon $startDate): array
    {
        return self::where('created_at', '>=', $startDate)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();
    }

    /**
     * Get daily activity
     */
    private static function getDailyActivity(int $days): array
    {
        $activity = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = self::whereDate('created_at', $date)->count();
            
            $activity[] = [
                'date' => $date->format('Y-m-d'),
                'formatted_date' => $date->format('M j'),
                'count' => $count,
            ];
        }
        
        return $activity;
    }

    /**
     * Get user activity
     */
    private static function getUserActivity(Carbon $startDate): array
    {
        return self::with('user')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('user_id, COUNT(*) as actions_count')
            ->groupBy('user_id')
            ->orderByDesc('actions_count')
            ->limit(10)
            ->get()
            ->map(function ($history) {
                return [
                    'user_id' => $history->user_id,
                    'user_name' => $history->user?->name ?? 'Unknown User',
                    'actions_count' => $history->actions_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get story activity
     */
    private static function getStoryActivity(Carbon $startDate): array
    {
        return self::with('story')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('story_id, COUNT(*) as actions_count')
            ->groupBy('story_id')
            ->orderByDesc('actions_count')
            ->limit(10)
            ->get()
            ->map(function ($history) {
                return [
                    'story_id' => $history->story_id,
                    'story_title' => Str::limit($history->story?->title ?? 'Unknown Story', 50),
                    'actions_count' => $history->actions_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get impact analysis
     */
    private static function getImpactAnalysis(Carbon $startDate): array
    {
        $highImpact = self::where('created_at', '>=', $startDate)
            ->whereIn('action', [self::ACTION_PUBLISHED, self::ACTION_UNPUBLISHED, self::ACTION_EXPIRED])
            ->count();
            
        $mediumImpact = self::where('created_at', '>=', $startDate)
            ->whereIn('action', [self::ACTION_SCHEDULED, self::ACTION_RESCHEDULED])
            ->count();
            
        $lowImpact = self::where('created_at', '>=', $startDate)
            ->whereIn('action', [self::ACTION_UPDATED, self::ACTION_EXTENDED])
            ->count();

        return [
            'high_impact' => $highImpact,
            'medium_impact' => $mediumImpact,
            'low_impact' => $lowImpact,
            'total_impact_actions' => $highImpact + $mediumImpact + $lowImpact,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | UTILITY METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Create a publishing history record
     */
    public static function recordAction(
        int $storyId,
        int $userId,
        string $action,
        ?array $previousData = null,
        ?array $newData = null,
        ?string $notes = null,
        ?array $changedFields = null
    ): self {
        return self::create([
            'story_id' => $storyId,
            'user_id' => $userId,
            'action' => $action,
            'previous_active_status' => $previousData['active'] ?? null,
            'new_active_status' => $newData['active'] ?? null,
            'previous_active_from' => $previousData['active_from'] ?? null,
            'previous_active_until' => $previousData['active_until'] ?? null,
            'new_active_from' => $newData['active_from'] ?? null,
            'new_active_until' => $newData['active_until'] ?? null,
            'notes' => $notes,
            'changed_fields' => $changedFields,
        ]);
    }

    /**
     * Get story's publishing timeline
     */
    public static function getStoryTimeline(int $storyId): array
    {
        return self::with('user')
            ->where('story_id', $storyId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($history) {
                return [
                    'id' => $history->id,
                    'action' => $history->action,
                    'formatted_action' => $history->formatted_action,
                    'action_color' => $history->action_color,
                    'action_icon' => $history->action_icon,
                    'user_name' => $history->user?->name ?? 'Unknown User',
                    'status_change' => $history->status_change,
                    'schedule_change' => $history->schedule_change,
                    'changes_summary' => $history->changes_summary,
                    'notes' => $history->notes,
                    'impact_level' => $history->impact_level,
                    'created_at' => $history->created_at,
                    'formatted_date' => $history->created_at->format('M j, Y H:i'),
                    'relative_time' => $history->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    /**
     * Clear analytics cache
     */
    public function clearAnalyticsCache(): void
    {
        $patterns = [
            'publishing_analytics_7',
            'publishing_analytics_30',
            'publishing_analytics_90',
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Get validation rules for creating history records
     *
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'story_id' => 'required|exists:stories,id',
            'user_id' => 'required|exists:users,id',
            'action' => 'required|in:' . implode(',', self::VALID_ACTIONS),
            'previous_active_status' => 'nullable|boolean',
            'new_active_status' => 'nullable|boolean',
            'previous_active_from' => 'nullable|date',
            'previous_active_until' => 'nullable|date',
            'new_active_from' => 'nullable|date',
            'new_active_until' => 'nullable|date|after_or_equal:new_active_from',
            'notes' => 'nullable|string|max:1000',
            'changed_fields' => 'nullable|array',
            'ip_address' => 'nullable|ip',
            'user_agent' => 'nullable|string|max:500',
        ];
    }

    /**
     * Check if action is valid
     */
    public static function isValidAction(string $action): bool
    {
        return in_array($action, self::VALID_ACTIONS, true);
    }

    /**
     * Get Filament-friendly display name
     */
    public function getFilamentName(): string
    {
        return $this->formatted_action . ' - ' . ($this->story?->title ?? 'Unknown Story');
    }
}