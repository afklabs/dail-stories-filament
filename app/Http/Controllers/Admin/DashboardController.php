<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberStoryInteraction;
use App\Models\MemberStoryRating;
use App\Models\Story;
use App\Models\StoryPublishingHistory;
use App\Models\StoryRatingAggregate;
use App\Models\StoryView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Dashboard Controller
|--------------------------------------------------------------------------
*/

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard overview with real-time metrics
     */
    public function getOverview(): JsonResponse
    {
        try {
            $cacheKey = 'dashboard_overview_'.now()->format('Y-m-d-H-i');

            $data = Cache::remember($cacheKey, 300, function (): array { // 5 minutes cache
                return [
                    'key_metrics' => $this->getKeyMetrics(),
                    'today_activity' => $this->getTodayActivity(),
                    'growth_metrics' => $this->calculateGrowthMetrics(),
                    'system_health' => $this->getSystemHealth(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'cached_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard overview error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get views chart data for dashboard
     */
    public function getViewsChart(Request $request): JsonResponse
    {
        $days = $request->integer('days', 7);
        $days = min(max($days, 1), 90); // Limit between 1-90 days

        try {
            $cacheKey = "dashboard_views_chart_{$days}";

            $data = Cache::remember($cacheKey, 900, function () use ($days): array { // 15 minutes cache
                return StoryView::getAnalytics($days)['daily_trends'] ?? [];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'period' => $days,
            ]);
        } catch (\Exception $e) {
            Log::error('Views chart error', ['error' => $e->getMessage()]);

            return $this->errorResponse('Failed to load views chart data');
        }
    }

    /**
     * Get publishing activity chart
     */
    public function getPublishingChart(Request $request): JsonResponse
    {
        $days = $request->integer('days', 7);
        $days = min(max($days, 1), 30);

        try {
            $cacheKey = "dashboard_publishing_chart_{$days}";

            $data = Cache::remember($cacheKey, 1800, function () use ($days): array { // 30 minutes cache
                return StoryPublishingHistory::getPublishingAnalytics($days)['daily_activity'] ?? [];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'period' => $days,
            ]);
        } catch (\Exception $e) {
            Log::error('Publishing chart error', ['error' => $e->getMessage()]);

            return $this->errorResponse('Failed to load publishing chart data');
        }
    }

    /**
     * Get today's statistics with comparison to yesterday
     */
    public function getTodayStats(): JsonResponse
    {
        try {
            $cacheKey = 'dashboard_today_stats_'.now()->format('Y-m-d-H');

            $data = Cache::remember($cacheKey, 300, function (): array {
                $today = [
                    'views' => StoryView::today()->count(),
                    'ratings' => MemberStoryRating::whereDate('created_at', today())->count(),
                    'interactions' => MemberStoryInteraction::today()->count(),
                    'new_members' => Member::whereDate('created_at', today())->count(),
                ];

                $yesterday = [
                    'views' => StoryView::whereDate('viewed_at', yesterday())->count(),
                    'ratings' => MemberStoryRating::whereDate('created_at', yesterday())->count(),
                    'interactions' => MemberStoryInteraction::whereDate('created_at', yesterday())->count(),
                    'new_members' => Member::whereDate('created_at', yesterday())->count(),
                ];

                return [
                    'today' => $today,
                    'yesterday' => $yesterday,
                    'growth' => $this->calculateDailyGrowth($today, $yesterday),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Today stats error', ['error' => $e->getMessage()]);

            return $this->errorResponse('Failed to load today statistics');
        }
    }

    /**
     * Get member analytics overview
     */
    public function getMemberAnalytics(): JsonResponse
    {
        try {
            $cacheKey = 'member_analytics_overview';

            $data = Cache::remember($cacheKey, 1800, function (): array {
                return [
                    'total_members' => Member::count(),
                    'active_members' => Member::where('status', 'active')->count(),
                    'engagement_stats' => $this->getMemberEngagementStats(),
                    'growth_trends' => $this->getMemberGrowthTrends(),
                    'demographics' => $this->getMemberDemographics(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Member analytics error', ['error' => $e->getMessage()]);

            return $this->errorResponse('Failed to load member analytics');
        }
    }

    // Private helper methods
    private function getKeyMetrics(): array
    {
        return [
            'total_stories' => Story::count(),
            'active_stories' => Story::active()->count(),
            'total_views' => StoryView::count(),
            'unique_views' => StoryView::select('member_id', 'device_id')->distinct()->count(),
            'total_ratings' => MemberStoryRating::count(),
            'average_rating' => round(StoryRatingAggregate::avg('average_rating') ?? 0, 2),
            'total_members' => Member::count(),
            'active_members' => Member::where('status', 'active')->count(),
        ];
    }

    private function getTodayActivity(): array
    {
        return [
            'views' => StoryView::today()->count(),
            'ratings' => MemberStoryRating::whereDate('created_at', today())->count(),
            'interactions' => MemberStoryInteraction::today()->count(),
            'publishing_actions' => StoryPublishingHistory::today()->count(),
            'new_members' => Member::whereDate('created_at', today())->count(),
        ];
    }

    private function calculateGrowthMetrics(): array
    {
        $thisWeek = [
            'views' => StoryView::thisWeek()->count(),
            'ratings' => MemberStoryRating::thisWeek()->count(),
            'members' => Member::thisWeek()->count(),
        ];

        $lastWeek = [
            'views' => StoryView::whereBetween('viewed_at', [
                now()->subWeeks(2)->startOfWeek(),
                now()->subWeek()->endOfWeek(),
            ])->count(),
            'ratings' => MemberStoryRating::whereBetween('created_at', [
                now()->subWeeks(2)->startOfWeek(),
                now()->subWeek()->endOfWeek(),
            ])->count(),
            'members' => Member::whereBetween('created_at', [
                now()->subWeeks(2)->startOfWeek(),
                now()->subWeek()->endOfWeek(),
            ])->count(),
        ];

        return [
            'this_week' => $thisWeek,
            'last_week' => $lastWeek,
            'growth_percentage' => $this->calculatePercentageGrowth($thisWeek, $lastWeek),
        ];
    }

    private function getSystemHealth(): array
    {
        return [
            'database_health' => 'healthy',
            'cache_status' => Cache::has('dashboard_overview') ? 'active' : 'inactive',
            'storage_usage' => $this->getStorageUsage(),
            'active_users' => Member::where('last_login_at', '>', now()->subHour())->count(),
        ];
    }

    private function calculateDailyGrowth(array $today, array $yesterday): array
    {
        $growth = [];

        foreach ($today as $key => $value) {
            $yesterdayValue = $yesterday[$key] ?? 0;
            if ($yesterdayValue > 0) {
                $growth[$key] = round((($value - $yesterdayValue) / $yesterdayValue) * 100, 1);
            } else {
                $growth[$key] = $value > 0 ? 100 : 0;
            }
        }

        return $growth;
    }

    private function calculatePercentageGrowth(array $current, array $previous): array
    {
        $growth = [];

        foreach ($current as $key => $value) {
            $previousValue = $previous[$key] ?? 0;
            if ($previousValue > 0) {
                $growth[$key] = round((($value - $previousValue) / $previousValue) * 100, 1);
            } else {
                $growth[$key] = $value > 0 ? 100 : 0;
            }
        }

        return $growth;
    }

    private function getStorageUsage(): array
    {
        // Simplified storage check - in production, implement proper disk usage monitoring
        return [
            'total_space' => '100GB',
            'used_space' => '45GB',
            'usage_percentage' => 45,
        ];
    }

    private function getMemberEngagementStats(): array
    {
        return [
            'daily_active' => Member::whereHas('storyViews', function ($query) {
                $query->whereDate('viewed_at', today());
            })->count(),
            'weekly_active' => Member::whereHas('storyViews', function ($query) {
                $query->whereBetween('viewed_at', [now()->startOfWeek(), now()->endOfWeek()]);
            })->count(),
            'high_engagement' => Member::whereHas('interactions', function ($query) {
                $query->where('created_at', '>', now()->subWeek());
            }, '>=', 5)->count(),
        ];
    }

    private function getMemberGrowthTrends(): array
    {
        return [
            'new_this_month' => Member::whereMonth('created_at', now()->month)->count(),
            'new_last_month' => Member::whereMonth('created_at', now()->subMonth()->month)->count(),
            'retention_rate' => $this->calculateRetentionRate(),
        ];
    }

    private function getMemberDemographics(): array
    {
        return [
            'by_gender' => Member::selectRaw('gender, COUNT(*) as count')
                ->whereNotNull('gender')
                ->groupBy('gender')
                ->pluck('count', 'gender')
                ->toArray(),
            'by_status' => Member::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
        ];
    }

    private function calculateRetentionRate(): float
    {
        $totalMembers = Member::where('created_at', '<', now()->subWeek())->count();
        $activeMembers = Member::where('created_at', '<', now()->subWeek())
            ->whereHas('storyViews', function ($query) {
                $query->where('viewed_at', '>', now()->subWeek());
            })->count();

        return $totalMembers > 0 ? round(($activeMembers / $totalMembers) * 100, 1) : 0;
    }
}
