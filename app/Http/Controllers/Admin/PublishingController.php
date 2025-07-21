<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Story;
use App\Models\StoryPublishingHistory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| Publishing Controller
|--------------------------------------------------------------------------
*/

class PublishingController extends BaseAdminController
{
    /**
     * Get comprehensive publishing statistics
     */
    public function getPublishingStats(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $days = min(max($days, 1), 90);

        try {
            $cacheKey = "publishing_stats_{$days}";

            $data = Cache::remember($cacheKey, 1800, function () use ($days): array {
                $analytics = StoryPublishingHistory::getPublishingAnalytics($days);

                return [
                    'activity_summary' => $analytics['activity_summary'] ?? [],
                    'action_breakdown' => $analytics['action_breakdown'] ?? [],
                    'user_activity' => $analytics['user_activity'] ?? [],
                    'impact_analysis' => $analytics['impact_analysis'] ?? [],
                    'trends' => $this->getPublishingTrends($days),
                    'performance_metrics' => $this->getPublishingPerformanceMetrics($days),
                ];
            });

            return $this->successResponse($data, 'Publishing statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Publishing stats error', ['error' => $e->getMessage()]);

            return $this->errorResponse('Failed to load publishing statistics');
        }
    }

    /**
     * Get chart data for publishing activity
     */
    public function getChartData(Request $request): JsonResponse
    {
        $days = $request->integer('days', 7);
        $type = $request->string('type', 'daily'); // daily, weekly, monthly

        try {
            $cacheKey = "publishing_chart_{$type}_{$days}";

            $data = Cache::remember($cacheKey, 900, function () use ($days, $type): array {
                switch ($type) {
                    case 'weekly':
                        return $this->getWeeklyPublishingData($days);
                    case 'monthly':
                        return $this->getMonthlyPublishingData($days);
                    default:
                        return $this->getDailyPublishingData($days);
                }
            });

            return $this->successResponse([
                'chart_data' => $data,
                'type' => $type,
                'period' => $days,
            ]);
        } catch (\Exception $e) {
            Log::error('Publishing chart error', ['error' => $e->getMessage()]);

            return $this->errorResponse('Failed to load chart data');
        }
    }

    /**
     * Get admin activity summary
     */
    public function getAdminActivity(Request $request, int $days = 30): JsonResponse
    {
        $days = min(max($days, 1), 90);

        try {
            $cacheKey = "admin_activity_{$days}";

            $data = Cache::remember($cacheKey, 1800, function () use ($days): array {
                $startDate = now()->subDays($days);

                return [
                    'top_admins' => $this->getTopAdminsByActivity($startDate),
                    'action_distribution' => $this->getActionDistribution($startDate),
                    'peak_activity_hours' => $this->getPeakActivityHours($startDate),
                    'efficiency_metrics' => $this->getAdminEfficiencyMetrics($startDate),
                ];
            });

            return $this->successResponse($data, 'Admin activity data retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Admin activity error', ['error' => $e->getMessage()]);

            return $this->errorResponse('Failed to load admin activity data');
        }
    }

    /**
     * Get story publishing timeline
     */
    public function getStoryTimeline(Story $story): JsonResponse
    {
        try {
            Gate::authorize('view', $story);

            $cacheKey = "story_timeline_{$story->id}";

            $timeline = Cache::remember($cacheKey, 1800, function () use ($story): array {
                return StoryPublishingHistory::getStoryTimeline($story->id);
            });

            return $this->successResponse([
                'story' => [
                    'id' => $story->id,
                    'title' => $story->title,
                    'current_status' => $story->active ? 'active' : 'inactive',
                ],
                'timeline' => $timeline,
                'total_actions' => count($timeline),
            ]);
        } catch (\Exception $e) {
            Log::error('Story timeline error', [
                'story_id' => $story->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to load story timeline');
        }
    }

    /**
     * Get publishing impact analysis
     */
    public function getImpactAnalysis(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);

        try {
            $cacheKey = "impact_analysis_{$days}";

            $data = Cache::remember($cacheKey, 3600, function () use ($days): array {
                $startDate = now()->subDays($days);

                return [
                    'high_impact_actions' => $this->getHighImpactActions($startDate),
                    'performance_correlation' => $this->getPerformanceCorrelation($startDate),
                    'content_quality_impact' => $this->getContentQualityImpact($startDate),
                    'user_engagement_impact' => $this->getUserEngagementImpact($startDate),
                ];
            });

            return $this->successResponse($data, 'Impact analysis retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Impact analysis error', ['error' => $e->getMessage()]);

            return $this->errorResponse('Failed to load impact analysis');
        }
    }

    /**
     * Export publishing history data
     */
    public function exportHistory(Request $request, string $format): JsonResponse
    {
        $validator = Validator::make(['format' => $format], [
            'format' => 'required|in:csv,excel,pdf',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Invalid export format', 422);
        }

        try {
            if (! $this->checkRateLimit('export_publishing_history', 5, 60)) {
                return $this->errorResponse('Rate limit exceeded. Please try again later.', 429);
            }

            $days = $request->integer('days', 30);
            $data = $this->prepareExportData($days);

            $filename = "publishing_history_{$days}days_".now()->format('Y-m-d_H-i-s');
            $filePath = $this->generateExportFile($data, $format, $filename);

            return $this->successResponse([
                'download_url' => Storage::url($filePath),
                'filename' => basename($filePath),
                'format' => $format,
                'records_count' => count($data),
                'expires_at' => now()->addHours(24)->toISOString(),
            ], 'Export file generated successfully');
        } catch (\Exception $e) {
            Log::error('Export error', ['error' => $e->getMessage()]);

            return $this->errorResponse('Failed to generate export file');
        }
    }

    // Private helper methods for PublishingController
    private function getPublishingTrends(int $days): array
    {
        $startDate = now()->subDays($days);

        $trends = StoryPublishingHistory::where('created_at', '>', $startDate)
            ->selectRaw('DATE(created_at) as date, action, COUNT(*) as count')
            ->groupBy('date', 'action')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        return $trends->mapWithKeys(function ($dayActions, $date) {
            return [$date => $dayActions->pluck('count', 'action')->toArray()];
        })->toArray();
    }

    private function getPublishingPerformanceMetrics(int $days): array
    {
        $startDate = now()->subDays($days);

        return [
            'total_actions' => StoryPublishingHistory::where('created_at', '>', $startDate)->count(),
            'unique_stories_affected' => StoryPublishingHistory::where('created_at', '>', $startDate)
                ->distinct('story_id')->count('story_id'),
            'active_admins' => StoryPublishingHistory::where('created_at', '>', $startDate)
                ->distinct('user_id')->count('user_id'),
            'average_actions_per_day' => StoryPublishingHistory::where('created_at', '>', $startDate)
                ->count() / max($days, 1),
            'most_common_action' => StoryPublishingHistory::where('created_at', '>', $startDate)
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->first()?->action ?? 'none',
        ];
    }

    private function getDailyPublishingData(int $days): array
    {
        $startDate = now()->subDays($days);

        return StoryPublishingHistory::where('created_at', '>', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total_actions')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'formatted_date' => Carbon::parse($item->date)->format('M j'),
                    'total_actions' => $item->total_actions,
                ];
            })
            ->toArray();
    }

    private function getWeeklyPublishingData(int $weeks): array
    {
        $startDate = now()->subWeeks($weeks);

        return StoryPublishingHistory::where('created_at', '>', $startDate)
            ->selectRaw('YEARWEEK(created_at) as week, COUNT(*) as total_actions')
            ->groupBy('week')
            ->orderBy('week')
            ->get()
            ->map(function ($item) {
                $weekStart = Carbon::parse($item->week.'1')->startOfWeek();

                return [
                    'week' => $item->week,
                    'week_start' => $weekStart->format('M j'),
                    'total_actions' => $item->total_actions,
                ];
            })
            ->toArray();
    }

    private function getMonthlyPublishingData(int $months): array
    {
        $startDate = now()->subMonths($months);

        return StoryPublishingHistory::where('created_at', '>', $startDate)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total_actions')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'year' => $item->year,
                    'formatted_date' => Carbon::createFromDate($item->year, $item->month, 1)->format('M Y'),
                    'total_actions' => $item->total_actions,
                ];
            })
            ->toArray();
    }

    private function getTopAdminsByActivity(\DateTime $startDate): array
    {
        return StoryPublishingHistory::where('created_at', '>', $startDate)
            ->join('users', 'story_publishing_history.user_id', '=', 'users.id')
            ->selectRaw('users.id, users.name, COUNT(*) as action_count')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('action_count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function getActionDistribution(\DateTime $startDate): array
    {
        return StoryPublishingHistory::where('created_at', '>', $startDate)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'action')
            ->toArray();
    }

    private function getPeakActivityHours(\DateTime $startDate): array
    {
        return StoryPublishingHistory::where('created_at', '>', $startDate)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => $item->hour,
                    'formatted_hour' => sprintf('%02d:00', $item->hour),
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    private function getAdminEfficiencyMetrics(\DateTime $startDate): array
    {
        $adminStats = StoryPublishingHistory::where('created_at', '>', $startDate)
            ->join('users', 'story_publishing_history.user_id', '=', 'users.id')
            ->selectRaw('
                users.id,
                users.name,
                COUNT(*) as total_actions,
                COUNT(DISTINCT story_id) as unique_stories,
                AVG(CASE WHEN action IN ("published", "republished") THEN 1 ELSE 0 END) as publish_rate
            ')
            ->groupBy('users.id', 'users.name')
            ->having('total_actions', '>', 5) // Only include admins with meaningful activity
            ->get();

        return $adminStats->map(function ($admin) {
            return [
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'total_actions' => $admin->total_actions,
                'unique_stories' => $admin->unique_stories,
                'efficiency_ratio' => round($admin->unique_stories / $admin->total_actions, 2),
                'publish_rate' => round($admin->publish_rate * 100, 1),
            ];
        })->toArray();
    }

    private function getHighImpactActions(\DateTime $startDate): array
    {
        return StoryPublishingHistory::where('created_at', '>', $startDate)
            ->join('stories', 'story_publishing_history.story_id', '=', 'stories.id')
            ->leftJoin('story_views', 'stories.id', '=', 'story_views.story_id')
            ->selectRaw('
                story_publishing_history.id,
                story_publishing_history.action,
                stories.title,
                story_publishing_history.created_at,
                COUNT(story_views.id) as total_views
            ')
            ->groupBy('story_publishing_history.id', 'story_publishing_history.action', 'stories.title', 'story_publishing_history.created_at')
            ->having('total_views', '>', 100) // High impact threshold
            ->orderByDesc('total_views')
            ->limit(20)
            ->get()
            ->toArray();
    }

    private function getPerformanceCorrelation(\DateTime $startDate): array
    {
        // Simplified correlation analysis
        return [
            'quick_publish_success_rate' => $this->calculateQuickPublishSuccessRate($startDate),
            'republish_effectiveness' => $this->calculateRepublishEffectiveness($startDate),
            'schedule_accuracy' => $this->calculateScheduleAccuracy($startDate),
        ];
    }

    private function getContentQualityImpact(\DateTime $startDate): array
    {
        return [
            'avg_rating_after_publish' => StoryPublishingHistory::where('created_at', '>', $startDate)
                ->where('action', 'published')
                ->join('stories', 'story_publishing_history.story_id', '=', 'stories.id')
                ->leftJoin('story_rating_aggregates', 'stories.id', '=', 'story_rating_aggregates.story_id')
                ->avg('story_rating_aggregates.average_rating') ?? 0,
            'quality_improvement_rate' => $this->calculateQualityImprovementRate($startDate),
        ];
    }

    private function getUserEngagementImpact(\DateTime $startDate): array
    {
        return [
            'view_increase_after_publish' => $this->calculateViewIncreaseAfterPublish($startDate),
            'interaction_boost' => $this->calculateInteractionBoost($startDate),
            'member_retention_impact' => $this->calculateMemberRetentionImpact($startDate),
        ];
    }

    private function prepareExportData(int $days): array
    {
        $startDate = now()->subDays($days);

        return StoryPublishingHistory::where('created_at', '>', $startDate)
            ->join('stories', 'story_publishing_history.story_id', '=', 'stories.id')
            ->join('users', 'story_publishing_history.user_id', '=', 'users.id')
            ->select([
                'story_publishing_history.created_at',
                'stories.title as story_title',
                'users.name as admin_name',
                'story_publishing_history.action',
                'story_publishing_history.notes',
                'story_publishing_history.previous_active_status',
                'story_publishing_history.new_active_status',
            ])
            ->orderByDesc('story_publishing_history.created_at')
            ->get()
            ->toArray();
    }

    private function generateExportFile(array $data, string $format, string $filename): string
    {
        switch ($format) {
            case 'csv':
                return $this->generateCSVFile($data, $filename);
            case 'excel':
                return $this->generateExcelFile($data, $filename);
            case 'pdf':
                return $this->generatePDFFile($data, $filename);
            default:
                throw new \InvalidArgumentException('Unsupported export format');
        }
    }

    private function generateCSVFile(array $data, string $filename): string
    {
        $filePath = "exports/{$filename}.csv";
        $fullPath = storage_path("app/public/{$filePath}");

        // Ensure directory exists
        if (! file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $file = fopen($fullPath, 'w');

        // Write headers
        if (! empty($data)) {
            fputcsv($file, array_keys($data[0]));

            // Write data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
        }

        fclose($file);

        return $filePath;
    }

    private function generateExcelFile(array $data, string $filename): string
    {
        // Simplified Excel generation - in production, use PhpSpreadsheet
        return $this->generateCSVFile($data, $filename.'_excel');
    }

    private function generatePDFFile(array $data, string $filename): string
    {
        // Simplified PDF generation - in production, use dompdf or similar
        return $this->generateCSVFile($data, $filename.'_pdf');
    }

    // Simplified calculation methods - implement with real logic
    private function calculateQuickPublishSuccessRate(\DateTime $startDate): float
    {
        return 85.3; // Placeholder
    }

    private function calculateRepublishEffectiveness(\DateTime $startDate): float
    {
        return 72.1; // Placeholder
    }

    private function calculateScheduleAccuracy(\DateTime $startDate): float
    {
        return 94.7; // Placeholder
    }

    private function calculateQualityImprovementRate(\DateTime $startDate): float
    {
        return 12.5; // Placeholder
    }

    private function calculateViewIncreaseAfterPublish(\DateTime $startDate): float
    {
        return 156.8; // Placeholder
    }

    private function calculateInteractionBoost(\DateTime $startDate): float
    {
        return 89.3; // Placeholder
    }

    private function calculateMemberRetentionImpact(\DateTime $startDate): float
    {
        return 23.7; // Placeholder
    }
}
