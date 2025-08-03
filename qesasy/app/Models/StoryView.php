<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * StoryView Model for Daily Stories App with Filament Integration
 *
 * Enhanced analytics and tracking system for story views with comprehensive
 * insights, performance metrics, and user behavior analysis.
 *
 * @property int $id
 * @property int $story_id
 * @property string|null $device_id
 * @property int|null $member_id
 * @property string|null $session_id
 * @property string|null $user_agent
 * @property string|null $ip_address
 * @property string|null $referrer
 * @property array|null $metadata
 * @property Carbon $viewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class StoryView extends Model
{
    use HasFactory;

    /**
     * Cache constants
     */
    private const CACHE_TTL = 300; // 5 minutes

    private const CACHE_TTL_ANALYTICS = 900; // 15 minutes

    private const CACHE_TTL_TRENDS = 1800; // 30 minutes

    /**
     * View type constants
     */
    private const VIEW_TYPE_MEMBER = 'member';

    private const VIEW_TYPE_GUEST = 'guest';

    private const VIEW_TYPE_ANONYMOUS = 'anonymous';

    /**
     * Analytics constants
     */
    private const RECENT_DAYS = 7;

    private const TRENDING_THRESHOLD = 10;

    private const POPULAR_THRESHOLD = 50;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'story_id',
        'device_id',
        'member_id',
        'session_id',
        'user_agent',
        'ip_address',
        'referrer',
        'metadata',
        'viewed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'story_id' => 'integer',
        'member_id' => 'integer',
        'metadata' => 'array',
        'viewed_at' => 'datetime',
    ];

    /**
     * Model boot method for event handling
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-fill viewed_at if not provided
        static::creating(function (self $view): void {
            if (! $view->viewed_at) {
                $view->viewed_at = Carbon::now();
            }
        });

        // Create member interaction when view is created
        static::created(function (self $view): void {
            if ($view->member_id) {
                MemberStoryInteraction::updateOrCreate(
                    [
                        'member_id' => $view->member_id,
                        'story_id' => $view->story_id,
                        'action' => 'view',
                    ],
                    [
                        'member_id' => $view->member_id,
                        'story_id' => $view->story_id,
                        'action' => 'view',
                    ]
                );
            }

            // Clear related caches
            $view->clearRelatedCache();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the story that this view belongs to.
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * Get the member who viewed this story.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the viewer type (member/guest/anonymous)
     */
    public function getViewerTypeAttribute(): string
    {
        if ($this->member_id) {
            return self::VIEW_TYPE_MEMBER;
        }

        if ($this->device_id) {
            return self::VIEW_TYPE_GUEST;
        }

        return self::VIEW_TYPE_ANONYMOUS;
    }

    /**
     * Get viewer display name
     */
    public function getViewerNameAttribute(): string
    {
        if ($this->member) {
            return $this->member->name;
        }

        if ($this->device_id) {
            return 'Guest ('.substr($this->device_id, 0, 8).')';
        }

        return 'Anonymous Viewer';
    }

    /**
     * Get browser information from user agent
     */
    public function getBrowserInfoAttribute(): array
    {
        if (! $this->user_agent) {
            return ['browser' => 'Unknown', 'platform' => 'Unknown'];
        }

        $userAgent = $this->user_agent;
        $browser = 'Unknown';
        $platform = 'Unknown';

        // Simple browser detection
        if (strpos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            $browser = 'Edge';
        }

        // Simple platform detection
        if (strpos($userAgent, 'Windows') !== false) {
            $platform = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $platform = 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $platform = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $platform = 'Android';
        } elseif (strpos($userAgent, 'iOS') !== false) {
            $platform = 'iOS';
        }

        return ['browser' => $browser, 'platform' => $platform];
    }

    /**
     * Check if view is from mobile device
     */
    public function getIsMobileAttribute(): bool
    {
        if (! $this->user_agent) {
            return false;
        }

        return preg_match('/Mobile|Android|iPhone|iPad/', $this->user_agent) === 1;
    }

    /**
     * Get formatted time ago
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->viewed_at->diffForHumans();
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for member views
     */
    public function scopeMembers(Builder $query): Builder
    {
        return $query->whereNotNull('member_id');
    }

    /**
     * Scope for guest views
     */
    public function scopeGuests(Builder $query): Builder
    {
        return $query->whereNull('member_id')->whereNotNull('device_id');
    }

    /**
     * Scope for anonymous views
     */
    public function scopeAnonymous(Builder $query): Builder
    {
        return $query->whereNull('member_id')->whereNull('device_id');
    }

    /**
     * Scope for recent views
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('viewed_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope for today's views
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('viewed_at', Carbon::today());
    }

    /**
     * Scope for this week's views
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('viewed_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ]);
    }

    /**
     * Scope for this month's views
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('viewed_at', Carbon::now()->month)
            ->whereYear('viewed_at', Carbon::now()->year);
    }

    /**
     * Scope for mobile views
     */
    public function scopeMobile(Builder $query): Builder
    {
        return $query->where('user_agent', 'REGEXP', 'Mobile|Android|iPhone|iPad');
    }

    /**
     * Scope for desktop views
     */
    public function scopeDesktop(Builder $query): Builder
    {
        return $query->where('user_agent', 'NOT REGEXP', 'Mobile|Android|iPhone|iPad')
            ->whereNotNull('user_agent');
    }

    /**
     * Scope by story
     */
    public function scopeByStory(Builder $query, int $storyId): Builder
    {
        return $query->where('story_id', $storyId);
    }

    /**
     * Scope by member
     */
    public function scopeByMember(Builder $query, int $memberId): Builder
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope for unique views (distinct device_id or member_id)
     */
    public function scopeUnique(Builder $query): Builder
    {
        return $query->select('*')
            ->whereIn('id', function ($subQuery) {
                $subQuery->select(DB::raw('MIN(id)'))
                    ->from('story_views')
                    ->groupBy(DB::raw('COALESCE(member_id, device_id), story_id'));
            });
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC ANALYTICS METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Get comprehensive view analytics
     */
    public static function getAnalytics(int $days = 30): array
    {
        return Cache::remember("view_analytics_{$days}", self::CACHE_TTL_ANALYTICS, function () use ($days): array {
            $startDate = Carbon::now()->subDays($days);

            return [
                'overview' => self::getOverviewStats($startDate),
                'audience' => self::getAudienceStats($startDate),
                'daily_trends' => self::getDailyTrends($days),
                'popular_stories' => self::getPopularStories($startDate),
                'device_analytics' => self::getDeviceAnalytics($startDate),
                'traffic_sources' => self::getTrafficSources($startDate),
            ];
        });
    }

    /**
     * Get overview statistics
     */
    private static function getOverviewStats(Carbon $startDate): array
    {
        $totalViews = self::where('viewed_at', '>=', $startDate)->count();
        $uniqueViews = self::where('viewed_at', '>=', $startDate)
            ->select(DB::raw('COUNT(DISTINCT COALESCE(member_id, device_id)) as count'))
            ->value('count') ?? 0;
        $memberViews = self::where('viewed_at', '>=', $startDate)->members()->count();
        $guestViews = self::where('viewed_at', '>=', $startDate)->guests()->count();
        $todayViews = self::today()->count();
        $yesterdayViews = self::whereDate('viewed_at', Carbon::yesterday())->count();

        $growth = $yesterdayViews > 0 ? (($todayViews - $yesterdayViews) / $yesterdayViews) * 100 : 0;

        return [
            'total_views' => $totalViews,
            'unique_views' => $uniqueViews,
            'member_views' => $memberViews,
            'guest_views' => $guestViews,
            'today_views' => $todayViews,
            'yesterday_views' => $yesterdayViews,
            'daily_growth' => round($growth, 1),
            'view_rate' => $totalViews > 0 ? round(($uniqueViews / $totalViews) * 100, 1) : 0,
        ];
    }

    /**
     * Get audience statistics
     */
    private static function getAudienceStats(Carbon $startDate): array
    {
        $totalViews = self::where('viewed_at', '>=', $startDate)->count();
        $memberViews = self::where('viewed_at', '>=', $startDate)->members()->count();
        $guestViews = self::where('viewed_at', '>=', $startDate)->guests()->count();
        $anonymousViews = self::where('viewed_at', '>=', $startDate)->anonymous()->count();

        return [
            'member_percentage' => $totalViews > 0 ? round(($memberViews / $totalViews) * 100, 1) : 0,
            'guest_percentage' => $totalViews > 0 ? round(($guestViews / $totalViews) * 100, 1) : 0,
            'anonymous_percentage' => $totalViews > 0 ? round(($anonymousViews / $totalViews) * 100, 1) : 0,
            'returning_viewers' => self::getReturningViewers($startDate),
            'new_viewers' => self::getNewViewers($startDate),
        ];
    }

    /**
     * Get daily trends
     */
    private static function getDailyTrends(int $days): array
    {
        $trends = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $totalViews = self::whereDate('viewed_at', $date)->count();
            $uniqueViews = self::whereDate('viewed_at', $date)
                ->select(DB::raw('COUNT(DISTINCT COALESCE(member_id, device_id)) as count'))
                ->value('count') ?? 0;

            $trends[] = [
                'date' => $date->format('Y-m-d'),
                'formatted_date' => $date->format('M j'),
                'total_views' => $totalViews,
                'unique_views' => $uniqueViews,
                'member_views' => self::whereDate('viewed_at', $date)->members()->count(),
                'guest_views' => self::whereDate('viewed_at', $date)->guests()->count(),
            ];
        }

        return $trends;
    }

    /**
     * Get popular stories
     */
    private static function getPopularStories(Carbon $startDate, int $limit = 10): array
    {
        return self::with('story')
            ->where('viewed_at', '>=', $startDate)
            ->select('story_id', DB::raw('COUNT(*) as view_count'), DB::raw('COUNT(DISTINCT COALESCE(member_id, device_id)) as unique_count'))
            ->groupBy('story_id')
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get()
            ->map(function ($view) {
                return [
                    'story_id' => $view->story_id,
                    'story_title' => $view->story?->title ?? 'Unknown Story',
                    'total_views' => $view->view_count,
                    'unique_views' => $view->unique_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get device analytics
     */
    private static function getDeviceAnalytics(Carbon $startDate): array
    {
        $totalViews = self::where('viewed_at', '>=', $startDate)->whereNotNull('user_agent')->count();
        $mobileViews = self::where('viewed_at', '>=', $startDate)->mobile()->count();
        $desktopViews = self::where('viewed_at', '>=', $startDate)->desktop()->count();

        return [
            'mobile_percentage' => $totalViews > 0 ? round(($mobileViews / $totalViews) * 100, 1) : 0,
            'desktop_percentage' => $totalViews > 0 ? round(($desktopViews / $totalViews) * 100, 1) : 0,
            'mobile_views' => $mobileViews,
            'desktop_views' => $desktopViews,
            'browser_breakdown' => self::getBrowserBreakdown($startDate),
            'platform_breakdown' => self::getPlatformBreakdown($startDate),
        ];
    }

    /**
     * Get traffic sources
     */
    private static function getTrafficSources(Carbon $startDate): array
    {
        return self::where('viewed_at', '>=', $startDate)
            ->whereNotNull('referrer')
            ->select('referrer', DB::raw('COUNT(*) as count'))
            ->groupBy('referrer')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($source) {
                return [
                    'referrer' => $source->referrer,
                    'count' => $source->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get story-specific analytics
     */
    public static function getStoryAnalytics(int $storyId): array
    {
        return Cache::remember("story_view_analytics_{$storyId}", self::CACHE_TTL_ANALYTICS, function () use ($storyId): array {
            $views = self::where('story_id', $storyId);
            $recentViews = self::where('story_id', $storyId)->recent(self::RECENT_DAYS);

            return [
                'total_views' => $views->count(),
                'unique_views' => $views->select(DB::raw('COUNT(DISTINCT COALESCE(member_id, device_id)) as count'))->value('count') ?? 0,
                'member_views' => $views->clone()->members()->count(),
                'guest_views' => $views->clone()->guests()->count(),
                'recent_views' => $recentViews->count(),
                'daily_average' => round($recentViews->count() / self::RECENT_DAYS, 1),
                'peak_day' => self::getStoryPeakDay($storyId),
                'view_timeline' => self::getStoryViewTimeline($storyId),
                'engagement_rate' => self::calculateStoryEngagementRate($storyId),
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | UTILITY METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Record a story view
     */
    public static function recordView(
        int $storyId,
        ?string $deviceId = null,
        ?int $memberId = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'story_id' => $storyId,
            'device_id' => $deviceId,
            'member_id' => $memberId,
            'session_id' => session()->getId(),
            'user_agent' => request()->header('User-Agent'),
            'ip_address' => request()->ip(),
            'referrer' => request()->header('Referer'),
            'metadata' => $metadata,
            'viewed_at' => Carbon::now(),
        ]);
    }

    /**
     * Get returning viewers count
     */
    private static function getReturningViewers(Carbon $startDate): int
    {
        return self::where('viewed_at', '>=', $startDate)
            ->select(DB::raw('COALESCE(member_id, device_id) as viewer_id'))
            ->groupBy('viewer_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }

    /**
     * Get new viewers count
     */
    private static function getNewViewers(Carbon $startDate): int
    {
        return self::where('viewed_at', '>=', $startDate)
            ->select(DB::raw('COALESCE(member_id, device_id) as viewer_id'))
            ->groupBy('viewer_id')
            ->havingRaw('COUNT(*) = 1')
            ->get()
            ->count();
    }

    /**
     * Get browser breakdown
     */
    private static function getBrowserBreakdown(Carbon $startDate): array
    {
        $views = self::where('viewed_at', '>=', $startDate)->whereNotNull('user_agent')->get();
        $browsers = ['Chrome' => 0, 'Firefox' => 0, 'Safari' => 0, 'Edge' => 0, 'Other' => 0];

        foreach ($views as $view) {
            $browserInfo = $view->browser_info;
            $browser = $browserInfo['browser'];

            if (array_key_exists($browser, $browsers)) {
                $browsers[$browser]++;
            } else {
                $browsers['Other']++;
            }
        }

        return $browsers;
    }

    /**
     * Get platform breakdown
     */
    private static function getPlatformBreakdown(Carbon $startDate): array
    {
        $views = self::where('viewed_at', '>=', $startDate)->whereNotNull('user_agent')->get();
        $platforms = ['Windows' => 0, 'macOS' => 0, 'Linux' => 0, 'Android' => 0, 'iOS' => 0, 'Other' => 0];

        foreach ($views as $view) {
            $browserInfo = $view->browser_info;
            $platform = $browserInfo['platform'];

            if (array_key_exists($platform, $platforms)) {
                $platforms[$platform]++;
            } else {
                $platforms['Other']++;
            }
        }

        return $platforms;
    }

    /**
     * Get story peak day
     */
    private static function getStoryPeakDay(int $storyId): ?array
    {
        $peakDay = self::where('story_id', $storyId)
            ->select(DB::raw('DATE(viewed_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(viewed_at)'))
            ->orderByDesc('count')
            ->first();

        return $peakDay ? [
            'date' => $peakDay->date,
            'views' => $peakDay->count,
        ] : null;
    }

    /**
     * Get story view timeline
     */
    private static function getStoryViewTimeline(int $storyId, int $days = 14): array
    {
        $timeline = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = self::where('story_id', $storyId)
                ->whereDate('viewed_at', $date)
                ->count();

            $timeline[] = [
                'date' => $date->format('Y-m-d'),
                'formatted_date' => $date->format('M j'),
                'views' => $count,
            ];
        }

        return $timeline;
    }

    /**
     * Calculate story engagement rate
     */
    private static function calculateStoryEngagementRate(int $storyId): float
    {
        $totalViews = self::where('story_id', $storyId)->count();

        if ($totalViews === 0) {
            return 0;
        }

        // Get interactions count for this story
        $interactions = MemberStoryInteraction::where('story_id', $storyId)
            ->whereNotIn('action', ['view'])
            ->count();

        return round(($interactions / $totalViews) * 100, 2);
    }

    /**
     * Clear related caches
     */
    public function clearRelatedCache(): void
    {
        $patterns = [
            'view_analytics_7',
            'view_analytics_30',
            'view_analytics_90',
            "story_view_analytics_{$this->story_id}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Get validation rules for view records
     *
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'story_id' => 'required|exists:stories,id',
            'device_id' => 'nullable|string|max:255',
            'member_id' => 'nullable|exists:members,id',
            'session_id' => 'nullable|string|max:255',
            'user_agent' => 'nullable|string|max:500',
            'ip_address' => 'nullable|ip',
            'referrer' => 'nullable|url|max:500',
            'metadata' => 'nullable|array',
            'viewed_at' => 'nullable|date',
        ];
    }

    /**
     * Get Filament-friendly display name
     */
    public function getFilamentName(): string
    {
        $storyTitle = $this->story?->title ?? 'Unknown Story';

        return "{$storyTitle} - {$this->viewer_name} ({$this->time_ago})";
    }
}
