<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Story, StoryView, Member, MemberStoryInteraction, MemberStoryRating, StoryRatingAggregate, StoryPublishingHistory, MemberReadingHistory};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Cache, Log, DB};
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class EnhancedAnalyticsController extends Controller
{
    /**
     * Get real-time analytics dashboard data
     */
    public function getRealTimeAnalytics(Request $request): JsonResponse
    {
        try {
            $period = $request->integer('period', 30);
            $period = min(max($period, 1), 90); // Limit between 1-90 days
            
            $data = Cache::remember("realtime_analytics_{$period}", 120, function () use ($period) {
                $dateFrom = now()->subDays($period);
                
                return [
                    'overview' => $this->getRealtimeOverview($dateFrom),
                    'trends' => $this->getRealtimeTrends($dateFrom, $period),
                    'engagement' => $this->getRealtimeEngagement($dateFrom),
                    'content_performance' => $this->getContentPerformance($dateFrom),
                    'user_behavior' => $this->getUserBehaviorMetrics($dateFrom),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'period_days' => $period,
                'generated_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Real-time analytics error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load real-time analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get story-specific analytics
     */
    public function getStoryAnalytics(Request $request, int $storyId): JsonResponse
    {
        try {
            $story = Story::findOrFail($storyId);
            $period = $request->integer('period', 30);
            
            $cacheKey = "story_analytics_{$storyId}_{$period}";
            
            $data = Cache::remember($cacheKey, 600, function () use ($story, $period) {
                $dateFrom = now()->subDays($period);
                
                return [
                    'story_info' => [
                        'id' => $story->id,
                        'title' => $story->title,
                        'category' => $story->category->name ?? 'Uncategorized',
                        'status' => $story->active ? 'active' : 'inactive',
                        'created_at' => $story->created_at->toISOString(),
                        'reading_time' => $story->reading_time_minutes,
                    ],
                    'performance_metrics' => $this->getStoryPerformanceMetrics($story, $dateFrom),
                    'engagement_details' => $this->getStoryEngagementDetails($story, $dateFrom),
                    'reading_analytics' => $this->getStoryReadingAnalytics($story, $dateFrom),
                    'sentiment_analysis' => $this->getStorySentimentAnalysis($story, $dateFrom),
                    'timeline_data' => $this->getStoryTimelineData($story, $period),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'period_days' => $period,
            ]);
        } catch (\Exception $e) {
            Log::error('Story analytics error', [
                'story_id' => $storyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load story analytics',
            ], 500);
        }
    }

    /**
     * Get audience insights
     */
    public function getAudienceInsights(Request $request): JsonResponse
    {
        try {
            $period = $request->integer('period', 30);
            
            $cacheKey = "audience_insights_{$period}";
            
            $data = Cache::remember($cacheKey, 900, function () use ($period) {
                $dateFrom = now()->subDays($period);
                
                return [
                    'demographics' => $this->getAudienceDemographics($dateFrom),
                    'behavior_patterns' => $this->getBehaviorPatterns($dateFrom),
                    'device_analytics' => $this->getDetailedDeviceAnalytics($dateFrom),
                    'engagement_segments' => $this->getEngagementSegments($dateFrom),
                    'retention_metrics' => $this->getRetentionMetrics($dateFrom),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'period_days' => $period,
            ]);
        } catch (\Exception $e) {
            Log::error('Audience insights error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load audience insights',
            ], 500);
        }
    }

    /**
     * Get content performance rankings
     */
    public function getContentRankings(Request $request): JsonResponse
    {
        try {
            $metric = $request->string('metric', 'views'); // views, rating, engagement
            $limit = $request->integer('limit', 20);
            $period = $request->integer('period', 30);
            
            $cacheKey = "content_rankings_{$metric}_{$limit}_{$period}";
            
            $data = Cache::remember($cacheKey, 600, function () use ($metric, $limit, $period) {
                $dateFrom = now()->subDays($period);
                
                return [
                    'top_performers' => $this->getTopPerformers($metric, $limit, $dateFrom),
                    'category_breakdown' => $this->getCategoryPerformance($metric, $dateFrom),
                    'trending_content' => $this->getTrendingContent($dateFrom),
                    'quality_insights' => $this->getQualityInsights($dateFrom),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'metric' => $metric,
                'period_days' => $period,
            ]);
        } catch (\Exception $e) {
            Log::error('Content rankings error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load content rankings',
            ], 500);
        }
    }

    /**
     * Get publishing analytics
     */
    public function getPublishingAnalytics(Request $request): JsonResponse
    {
        try {
            $period = $request->integer('period', 30);
            
            $cacheKey = "publishing_analytics_{$period}";
            
            $data = Cache::remember($cacheKey, 600, function () use ($period) {
                $dateFrom = now()->subDays($period);
                
                return [
                    'activity_summary' => $this->getPublishingActivitySummary($dateFrom),
                    'user_activity' => $this->getPublishingUserActivity($dateFrom),
                    'workflow_analytics' => $this->getWorkflowAnalytics($dateFrom),
                    'impact_analysis' => $this->getPublishingImpactAnalysis($dateFrom),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'period_days' => $period,
            ]);
        } catch (\Exception $e) {
            Log::error('Publishing analytics error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load publishing analytics',
            ], 500);
        }
    }

    // Private helper methods

    private function getRealtimeOverview(Carbon $dateFrom): array
    {
        return [
            'total_views' => StoryView::where('viewed_at', '>=', $dateFrom)->count(),
            'unique_viewers' => StoryView::where('viewed_at', '>=', $dateFrom)
                ->distinct('device_id')->count(),
            'member_views' => StoryView::where('viewed_at', '>=', $dateFrom)
                ->whereNotNull('member_id')->count(),
            'guest_views' => StoryView::where('viewed_at', '>=', $dateFrom)
                ->whereNull('member_id')->count(),
            'total_interactions' => MemberStoryInteraction::where('created_at', '>=', $dateFrom)->count(),
            'total_ratings' => MemberStoryRating::where('created_at', '>=', $dateFrom)->count(),
            'active_stories' => Story::active()->count(),
            'new_stories' => Story::where('created_at', '>=', $dateFrom)->count(),
        ];
    }

    private function getRealtimeTrends(Carbon $dateFrom, int $period): array
    {
        $days = min($period, 30); // Limit to 30 days for trends
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $nextDate = $date->copy()->addDay();

            $data[] = [
                'date' => $date->format('Y-m-d'),
                'formatted_date' => $date->format('M j'),
                'views' => StoryView::whereBetween('viewed_at', [$date, $nextDate])->count(),
                'unique_viewers' => StoryView::whereBetween('viewed_at', [$date, $nextDate])
                    ->distinct('device_id')->count(),
                'interactions' => MemberStoryInteraction::whereBetween('created_at', [$date, $nextDate])->count(),
                'ratings' => MemberStoryRating::whereBetween('created_at', [$date, $nextDate])->count(),
            ];
        }

        return $data;
    }

    private function getRealtimeEngagement(Carbon $dateFrom): array
    {
        $interactions = MemberStoryInteraction::where('created_at', '>=', $dateFrom)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->get()
            ->pluck('count', 'action');

        $totalViews = StoryView::where('viewed_at', '>=', $dateFrom)->count();
        $totalInteractions = $interactions->sum();

        return [
            'engagement_rate' => $totalViews > 0 ? round(($totalInteractions / $totalViews) * 100, 2) : 0,
            'interaction_breakdown' => $interactions->toArray(),
            'total_interactions' => $totalInteractions,
            'total_views' => $totalViews,
        ];
    }

    private function getContentPerformance(Carbon $dateFrom): array
    {
        return [
            'top_rated' => Story::join('story_rating_aggregates', 'stories.id', '=', 'story_rating_aggregates.story_id')
                ->where('story_rating_aggregates.total_ratings', '>=', 5)
                ->orderByDesc('story_rating_aggregates.average_rating')
                ->select('stories.id', 'stories.title', 'story_rating_aggregates.average_rating', 'story_rating_aggregates.total_ratings')
                ->limit(5)
                ->get(),
            
            'most_viewed' => Story::withCount(['storyViews as period_views' => function ($query) use ($dateFrom) {
                    $query->where('viewed_at', '>=', $dateFrom);
                }])
                ->orderByDesc('period_views')
                ->select('id', 'title')
                ->limit(5)
                ->get(),

            'most_engaged' => Story::withCount(['interactions as period_interactions' => function ($query) use ($dateFrom) {
                    $query->where('created_at', '>=', $dateFrom);
                }])
                ->orderByDesc('period_interactions')
                ->select('id', 'title')
                ->limit(5)
                ->get(),
        ];
    }

    private function getUserBehaviorMetrics(Carbon $dateFrom): array
    {
        $totalViews = StoryView::where('viewed_at', '>=', $dateFrom)->count();
        $memberViews = StoryView::where('viewed_at', '>=', $dateFrom)->whereNotNull('member_id')->count();
        
        return [
            'member_percentage' => $totalViews > 0 ? round(($memberViews / $totalViews) * 100, 1) : 0,
            'guest_percentage' => $totalViews > 0 ? round((($totalViews - $memberViews) / $totalViews) * 100, 1) : 0,
            'average_session_views' => $this->calculateAverageSessionViews($dateFrom),
            'return_visitor_rate' => $this->calculateReturnVisitorRate($dateFrom),
        ];
    }

    private function getStoryPerformanceMetrics(Story $story, Carbon $dateFrom): array
    {
        $views = $story->storyViews()->where('viewed_at', '>=', $dateFrom);
        $interactions = $story->interactions()->where('created_at', '>=', $dateFrom);
        
        return [
            'total_views' => $views->count(),
            'unique_viewers' => $views->distinct('device_id')->count(),
            'member_views' => $views->whereNotNull('member_id')->count(),
            'guest_views' => $views->whereNull('member_id')->count(),
            'total_interactions' => $interactions->count(),
            'engagement_rate' => $this->calculateEngagementRate($views->count(), $interactions->count()),
            'completion_rate' => $this->calculateCompletionRate($story),
            'average_reading_time' => $this->calculateAverageReadingTime($story),
        ];
    }

    private function getStoryEngagementDetails(Story $story, Carbon $dateFrom): array
    {
        $interactions = $story->interactions()
            ->where('created_at', '>=', $dateFrom)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->get()
            ->pluck('count', 'action');

        return [
            'interaction_breakdown' => $interactions->toArray(),
            'positive_interactions' => $interactions->only(['like', 'bookmark', 'share'])->sum(),
            'negative_interactions' => $interactions->only(['dislike', 'report'])->sum(),
            'engagement_quality_score' => $this->calculateEngagementQualityScore($interactions),
        ];
    }

    private function getStoryReadingAnalytics(Story $story, Carbon $dateFrom): array
    {
        $readingHistory = $story->readingHistory()
            ->where('last_read_at', '>=', $dateFrom);

        return [
            'total_readers' => $readingHistory->count(),
            'completed_reads' => $readingHistory->where('reading_progress', '>=', 100)->count(),
            'average_progress' => round($readingHistory->avg('reading_progress') ?? 0, 1),
            'average_time_spent' => round($readingHistory->avg('time_spent') ?? 0),
            'completion_rate' => $this->calculateCompletionRate($story),
        ];
    }

    private function getStorySentimentAnalysis(Story $story, Carbon $dateFrom): array
    {
        $interactions = $story->interactions()->where('created_at', '>=', $dateFrom);
        
        $positive = $interactions->clone()->whereIn('action', ['like', 'bookmark', 'share'])->count();
        $negative = $interactions->clone()->whereIn('action', ['dislike', 'report'])->count();
        $neutral = $interactions->clone()->whereIn('action', ['view'])->count();
        
        $total = $positive + $negative + $neutral;
        
        return [
            'sentiment_score' => $total > 0 ? round((($positive - $negative) / $total) * 100, 1) : 0,
            'positive_percentage' => $total > 0 ? round(($positive / $total) * 100, 1) : 0,
            'negative_percentage' => $total > 0 ? round(($negative / $total) * 100, 1) : 0,
            'neutral_percentage' => $total > 0 ? round(($neutral / $total) * 100, 1) : 0,
            'total_feedback' => $total,
        ];
    }

    private function getStoryTimelineData(Story $story, int $period): array
    {
        $days = min($period, 30);
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $nextDate = $date->copy()->addDay();

            $data[] = [
                'date' => $date->format('Y-m-d'),
                'views' => $story->storyViews()->whereBetween('viewed_at', [$date, $nextDate])->count(),
                'interactions' => $story->interactions()->whereBetween('created_at', [$date, $nextDate])->count(),
                'ratings' => $story->ratings()->whereBetween('created_at', [$date, $nextDate])->count(),
            ];
        }

        return $data;
    }

    private function getTopPerformers(string $metric, int $limit, Carbon $dateFrom): array
    {
        $query = Story::with(['category', 'ratingAggregate']);

        switch ($metric) {
            case 'views':
                $query->withCount(['storyViews as metric_value' => function ($q) use ($dateFrom) {
                    $q->where('viewed_at', '>=', $dateFrom);
                }])->orderByDesc('metric_value');
                break;
            
            case 'rating':
                $query->join('story_rating_aggregates', 'stories.id', '=', 'story_rating_aggregates.story_id')
                    ->where('story_rating_aggregates.total_ratings', '>=', 5)
                    ->orderByDesc('story_rating_aggregates.average_rating')
                    ->selectRaw('stories.*, story_rating_aggregates.average_rating as metric_value');
                break;
            
            case 'engagement':
                $query->withCount(['interactions as metric_value' => function ($q) use ($dateFrom) {
                    $q->where('created_at', '>=', $dateFrom);
                }])->orderByDesc('metric_value');
                break;
        }

        return $query->limit($limit)->get()->map(function ($story) use ($metric) {
            return [
                'id' => $story->id,
                'title' => $story->title,
                'category' => $story->category->name ?? 'Uncategorized',
                'metric_value' => $story->metric_value ?? 0,
                'metric_type' => $metric,
                'rating' => $story->ratingAggregate?->average_rating ?? 0,
                'total_ratings' => $story->ratingAggregate?->total_ratings ?? 0,
            ];
        })->toArray();
    }

    private function getCategoryPerformance(string $metric, Carbon $dateFrom): array
    {
        return DB::table('stories')
            ->join('story_categories', 'stories.category_id', '=', 'story_categories.id')
            ->leftJoin('story_views', function ($join) use ($dateFrom) {
                $join->on('stories.id', '=', 'story_views.story_id')
                     ->where('story_views.viewed_at', '>=', $dateFrom);
            })
            ->leftJoin('member_story_interactions', function ($join) use ($dateFrom) {
                $join->on('stories.id', '=', 'member_story_interactions.story_id')
                     ->where('member_story_interactions.created_at', '>=', $dateFrom);
            })
            ->selectRaw('
                story_categories.name as category,
                COUNT(DISTINCT stories.id) as story_count,
                COUNT(story_views.id) as total_views,
                COUNT(member_story_interactions.id) as total_interactions
            ')
            ->groupBy('story_categories.id', 'story_categories.name')
            ->orderByDesc('total_views')
            ->get()
            ->toArray();
    }

    private function getTrendingContent(Carbon $dateFrom): array
    {
        // Stories with significant growth in the last 7 days vs previous 7 days
        $recent = now()->subDays(7);
        $previous = now()->subDays(14);

        return Story::withCount([
            'storyViews as recent_views' => function ($query) use ($recent) {
                $query->where('viewed_at', '>=', $recent);
            },
            'storyViews as previous_views' => function ($query) use ($previous, $recent) {
                $query->whereBetween('viewed_at', [$previous, $recent]);
            }
        ])
        ->having('recent_views', '>', 10) // Minimum threshold
        ->get()
        ->map(function ($story) {
            $growth = $story->previous_views > 0 
                ? (($story->recent_views - $story->previous_views) / $story->previous_views) * 100
                : ($story->recent_views > 0 ? 100 : 0);
            
            return [
                'id' => $story->id,
                'title' => $story->title,
                'recent_views' => $story->recent_views,
                'previous_views' => $story->previous_views,
                'growth_percentage' => round($growth, 1),
            ];
        })
        ->where('growth_percentage', '>', 0)
        ->sortByDesc('growth_percentage')
        ->take(10)
        ->values()
        ->toArray();
    }

    private function getQualityInsights(Carbon $dateFrom): array
    {
        $ratingStats = StoryRatingAggregate::selectRaw('
            AVG(average_rating) as overall_average,
            COUNT(*) as total_rated_stories,
            SUM(CASE WHEN average_rating >= 4.5 THEN 1 ELSE 0 END) as excellent_count,
            SUM(CASE WHEN average_rating >= 3.5 AND average_rating < 4.5 THEN 1 ELSE 0 END) as good_count,
            SUM(CASE WHEN average_rating < 3.5 THEN 1 ELSE 0 END) as poor_count
        ')->first();

        return [
            'overall_average_rating' => round($ratingStats->overall_average ?? 0, 2),
            'total_rated_stories' => $ratingStats->total_rated_stories ?? 0,
            'quality_distribution' => [
                'excellent' => $ratingStats->excellent_count ?? 0,
                'good' => $ratingStats->good_count ?? 0,
                'poor' => $ratingStats->poor_count ?? 0,
            ],
            'content_health_score' => $this->calculateContentHealthScore($ratingStats),
        ];
    }

    // Utility methods
    private function calculateEngagementRate(int $views, int $interactions): float
    {
        return $views > 0 ? round(($interactions / $views) * 100, 2) : 0;
    }

    private function calculateCompletionRate(Story $story): float
    {
        $totalReaders = $story->readingHistory()->count();
        $completedReaders = $story->readingHistory()->where('reading_progress', '>=', 100)->count();
        
        return $totalReaders > 0 ? round(($completedReaders / $totalReaders) * 100, 1) : 0;
    }

    private function calculateAverageReadingTime(Story $story): float
    {
        return round($story->readingHistory()->avg('time_spent') ?? 0);
    }

    private function calculateAverageSessionViews(Carbon $dateFrom): float
    {
        $sessionViews = StoryView::where('viewed_at', '>=', $dateFrom)
            ->selectRaw('session_id, COUNT(*) as view_count')
            ->groupBy('session_id')
            ->get();

        return round($sessionViews->avg('view_count') ?? 0, 1);
    }

    private function calculateReturnVisitorRate(Carbon $dateFrom): float
    {
        $totalViewers = StoryView::where('viewed_at', '>=', $dateFrom)
            ->distinct('device_id')
            ->count();

        $returnVisitors = StoryView::where('viewed_at', '>=', $dateFrom)
            ->selectRaw('device_id, COUNT(*) as visit_count')
            ->groupBy('device_id')
            ->having('visit_count', '>', 1)
            ->count();

        return $totalViewers > 0 ? round(($returnVisitors / $totalViewers) * 100, 1) : 0;
    }

    private function calculateEngagementQualityScore(object $interactions): float
    {
        $positive = $interactions->get('like', 0) + $interactions->get('bookmark', 0) + $interactions->get('share', 0);
        $negative = $interactions->get('dislike', 0) + $interactions->get('report', 0);
        $total = $interactions->sum();

        if ($total === 0) return 0;

        return round((($positive - $negative) / $total) * 100, 1);
    }

    private function calculateContentHealthScore($ratingStats): float
    {
        if (!$ratingStats || $ratingStats->total_rated_stories === 0) return 0;

        $averageScore = ($ratingStats->overall_average / 5) * 50; // 50% weight
        $excellentRatio = ($ratingStats->excellent_count / $ratingStats->total_rated_stories) * 30; // 30% weight
        $goodRatio = ($ratingStats->good_count / $ratingStats->total_rated_stories) * 20; // 20% weight

        return round($averageScore + $excellentRatio + $goodRatio, 1);
    }
}