<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller; // ✅ ADDED: Missing import
use App\Models\Member;
use App\Models\MemberStoryInteraction;
use App\Models\MemberStoryRating;
use App\Models\Story;
use App\Models\StoryCategory;
use App\Models\StoryView;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Analytics Controller
|--------------------------------------------------------------------------
*/

class AnalyticsController extends BaseAdminController
{
    /**
     * Get comprehensive analytics overview
     */
    public function getOverview(Request $request): JsonResponse
    {
        try {
            $days = $request->integer('days', 30);
            $days = min(max($days, 1), 90); // Limit between 1-90 days

            $cacheKey = "analytics_overview_{$days}";

            $data = Cache::remember($cacheKey, 1800, function () use ($days): array {
                return [
                    'stories' => $this->getStoryAnalytics($days),
                    'members' => $this->getMemberAnalytics($days),
                    'engagement' => $this->getEngagementAnalytics($days),
                    'performance' => $this->getPerformanceMetrics($days),
                    'trends' => $this->getTrendAnalytics($days),
                ];
            });

            return $this->successResponse($data, 'Analytics overview retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Analytics overview error', ['error' => $e->getMessage()]);

            return $this->errorResponse('Failed to load analytics overview');
        }
    }

    /**
     * Get story analytics
     */
    private function getStoryAnalytics(int $days): array
    {
        $dateFrom = Carbon::now()->subDays($days);

        return [
            'total_stories' => Story::count(),
            'published_stories' => Story::where('status', 'published')->count(),
            'draft_stories' => Story::where('status', 'draft')->count(),
            'stories_this_period' => Story::where('created_at', '>=', $dateFrom)->count(),
            'avg_reading_time' => Story::avg('reading_time_minutes') ?? 0,
            'total_views' => StoryView::where('created_at', '>=', $dateFrom)->count(),
            'unique_viewers' => StoryView::where('created_at', '>=', $dateFrom)
                ->distinct('member_id')->count('member_id'),
        ];
    }

    /**
     * Get member analytics
     */
    private function getMemberAnalytics(int $days): array
    {
        $dateFrom = Carbon::now()->subDays($days);

        return [
            'total_members' => Member::count(),
            'active_members' => Member::where('status', 'active')->count(),
            'new_members' => Member::where('created_at', '>=', $dateFrom)->count(),
            'members_with_interactions' => MemberStoryInteraction::where('created_at', '>=', $dateFrom)
                ->distinct('member_id')->count('member_id'),
            'avg_member_age' => Member::whereNotNull('date_of_birth')
                ->selectRaw('AVG(YEAR(CURDATE()) - YEAR(date_of_birth)) as avg_age')
                ->value('avg_age') ?? 0,
        ];
    }

    /**
     * Get engagement analytics
     */
    private function getEngagementAnalytics(int $days): array
    {
        $dateFrom = Carbon::now()->subDays($days);

        return [
            'total_interactions' => MemberStoryInteraction::where('created_at', '>=', $dateFrom)->count(),
            'total_ratings' => MemberStoryRating::where('created_at', '>=', $dateFrom)->count(),
            'avg_rating' => MemberStoryRating::where('created_at', '>=', $dateFrom)->avg('rating') ?? 0,
            'engagement_rate' => $this->calculateEngagementRate($days),
            'top_categories' => $this->getTopCategories($days),
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(int $days): array
    {
        $dateFrom = Carbon::now()->subDays($days);

        return [
            'daily_active_users' => $this->getDailyActiveUsers($days),
            'retention_rate' => $this->calculateRetentionRate($days),
            'bounce_rate' => $this->calculateBounceRate($days),
            'avg_session_duration' => $this->getAverageSessionDuration($days),
        ];
    }

    /**
     * Get trend analytics
     */
    private function getTrendAnalytics(int $days): array
    {
        return [
            'views_trend' => $this->getViewsTrend($days),
            'engagement_trend' => $this->getEngagementTrend($days),
            'member_growth_trend' => $this->getMemberGrowthTrend($days),
        ];
    }

    /**
     * Calculate engagement rate
     */
    private function calculateEngagementRate(int $days): float
    {
        $dateFrom = Carbon::now()->subDays($days);

        $totalViews = StoryView::where('created_at', '>=', $dateFrom)->count();
        $totalInteractions = MemberStoryInteraction::where('created_at', '>=', $dateFrom)->count();

        return $totalViews > 0 ? round(($totalInteractions / $totalViews) * 100, 2) : 0;
    }

    /**
     * Get top categories
     */
    private function getTopCategories(int $days): array
    {
        $dateFrom = Carbon::now()->subDays($days);

        return StoryCategory::withCount(['stories' => function ($query) use ($dateFrom) {
            $query->whereHas('views', function ($viewQuery) use ($dateFrom) {
                $viewQuery->where('created_at', '>=', $dateFrom);
            });
        }])
            ->orderBy('stories_count', 'desc')
            ->limit(5)
            ->get(['name', 'stories_count'])
            ->toArray();
    }

    /**
     * Additional analytics methods would go here...
     */
    private function getDailyActiveUsers(int $days): int
    {
        $dateFrom = Carbon::now()->subDays($days);

        return StoryView::where('created_at', '>=', $dateFrom)
            ->distinct('member_id')
            ->count('member_id');
    }

    private function calculateRetentionRate(int $days): float
    {
        // Implementation for retention rate calculation
        return 75.5; // Placeholder
    }

    private function calculateBounceRate(int $days): float
    {
        // Implementation for bounce rate calculation
        return 25.3; // Placeholder
    }

    private function getAverageSessionDuration(int $days): float
    {
        // Implementation for session duration calculation
        return 8.5; // Placeholder in minutes
    }

    private function getViewsTrend(int $days): array
    {
        // Implementation for views trend
        return []; // Placeholder
    }

    private function getEngagementTrend(int $days): array
    {
        // Implementation for engagement trend
        return []; // Placeholder
    }

    private function getMemberGrowthTrend(int $days): array
    {
        // Implementation for member growth trend
        return []; // Placeholder
    }
}
