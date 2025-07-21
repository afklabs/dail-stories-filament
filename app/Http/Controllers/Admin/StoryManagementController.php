<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Story, StoryView, StoryRatingAggregate, StoryPublishingHistory, Member, MemberStoryRating, MemberStoryInteraction, Setting};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Cache, Log, DB, Validator, Gate};
use Illuminate\Database\Eloquent\{ModelNotFoundException, Builder};
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Story Management Controller
|--------------------------------------------------------------------------
*/

class StoryManagementController extends Controller
{
    /**
     * Quick publish a story with audit trail
     */
    public function quickPublish(Request $request, Story $story): JsonResponse
    {
        try {
            Gate::authorize('update', $story);
            
            DB::transaction(function () use ($story) {
                $previousStatus = $story->active;
                $previousActiveUntil = $story->active_until;
                
                $story->update([
                    'active' => true,
                    'active_from' => now(),
                    'active_until' => $story->active_until ?? now()->addDays(30),
                ]);
                
                // Record publishing history
                StoryPublishingHistory::recordAction(
                    $story->id,
                    auth()->id(),
                    'quick_published',
                    ['active' => $previousStatus, 'active_until' => $previousActiveUntil],
                    ['active' => true, 'active_until' => $story->active_until],
                    'Quick published via admin panel',
                    ['active', 'active_from', 'active_until']
                );
                
                // Clear related caches
                $this->clearStoryCache($story->id);
            });

            return response()->json([
                'success' => true,
                'message' => 'Story published successfully',
                'story' => [
                    'id' => $story->id,
                    'title' => $story->title,
                    'status' => 'published',
                    'active_until' => $story->active_until?->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Quick publish error', [
                'story_id' => $story->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to publish story', 500);
        }
    }

    /**
     * Quick unpublish a story with audit trail
     */
    public function quickUnpublish(Request $request, Story $story): JsonResponse
    {
        try {
            Gate::authorize('update', $story);
            
            DB::transaction(function () use ($story) {
                $previousStatus = $story->active;
                
                $story->update(['active' => false]);
                
                // Record publishing history
                StoryPublishingHistory::recordAction(
                    $story->id,
                    auth()->id(),
                    'quick_unpublished',
                    ['active' => $previousStatus],
                    ['active' => false],
                    'Quick unpublished via admin panel',
                    ['active']
                );
                
                $this->clearStoryCache($story->id);
            });

            return response()->json([
                'success' => true,
                'message' => 'Story unpublished successfully',
                'story' => [
                    'id' => $story->id,
                    'title' => $story->title,
                    'status' => 'unpublished',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Quick unpublish error', [
                'story_id' => $story->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to unpublish story', 500);
        }
    }

    /**
     * Extend publishing period for a story
     */
    public function extendPublishing(Request $request, Story $story): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => 'required|integer|min:1|max:365',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            Gate::authorize('update', $story);
            
            $days = $request->integer('days');
            $reason = $request->string('reason', 'Publishing period extended via admin panel');
            
            DB::transaction(function () use ($story, $days, $reason) {
                $previousActiveUntil = $story->active_until;
                $newActiveUntil = $story->active_until 
                    ? $story->active_until->addDays($days)
                    : now()->addDays($days);
                
                $story->update(['active_until' => $newActiveUntil]);
                
                // Record publishing history
                StoryPublishingHistory::recordAction(
                    $story->id,
                    auth()->id(),
                    'extended',
                    ['active_until' => $previousActiveUntil],
                    ['active_until' => $newActiveUntil],
                    $reason,
                    ['active_until']
                );
                
                $this->clearStoryCache($story->id);
            });

            return response()->json([
                'success' => true,
                'message' => "Publishing period extended by {$days} days",
                'story' => [
                    'id' => $story->id,
                    'title' => $story->title,
                    'active_until' => $story->fresh()->active_until?->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Extend publishing error', [
                'story_id' => $story->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to extend publishing period', 500);
        }
    }

    /**
     * Get stories expiring soon for monitoring
     */
    public function getExpiringSoon(Request $request): JsonResponse
    {
        $hours = $request->integer('hours', 24);
        $hours = min(max($hours, 1), 168); // 1 hour to 1 week
        
        try {
            $stories = Story::expiringSoon($hours)
                ->with(['category:id,name', 'ratingAggregate:story_id,average_rating,total_ratings'])
                ->select(['id', 'title', 'category_id', 'active_until', 'views'])
                ->orderBy('active_until')
                ->limit(50)
                ->get();
                
            $transformedStories = $stories->map(function ($story) {
                return [
                    'id' => $story->id,
                    'title' => $story->title,
                    'category' => $story->category?->name,
                    'expires_at' => $story->active_until?->toISOString(),
                    'expires_in_hours' => $story->active_until?->diffInHours(now()),
                    'views' => $story->views,
                    'rating' => $story->ratingAggregate?->average_rating,
                    'total_ratings' => $story->ratingAggregate?->total_ratings ?? 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedStories,
                'total_count' => $stories->count(),
                'period_hours' => $hours,
            ]);
        } catch (\Exception $e) {
            Log::error('Expiring stories error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to load expiring stories');
        }
    }

    /**
     * Bulk publish multiple stories
     */
    public function bulkPublish(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'story_ids' => 'required|array|min:1|max:100',
            'story_ids.*' => 'integer|exists:stories,id',
            'active_until_days' => 'nullable|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $storyIds = $request->input('story_ids');
            $activeUntilDays = $request->integer('active_until_days', 30);
            
            $results = DB::transaction(function () use ($storyIds, $activeUntilDays) {
                $successCount = 0;
                $failedStories = [];
                
                foreach ($storyIds as $storyId) {
                    try {
                        $story = Story::findOrFail($storyId);
                        Gate::authorize('update', $story);
                        
                        $story->update([
                            'active' => true,
                            'active_from' => now(),
                            'active_until' => now()->addDays($activeUntilDays),
                        ]);
                        
                        // Record publishing history
                        StoryPublishingHistory::recordAction(
                            $story->id,
                            auth()->id(),
                            'bulk_published',
                            ['active' => false],
                            ['active' => true],
                            'Bulk published via admin panel',
                            ['active', 'active_from', 'active_until']
                        );
                        
                        $this->clearStoryCache($story->id);
                        $successCount++;
                    } catch (\Exception $e) {
                        $failedStories[] = [
                            'story_id' => $storyId,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
                
                return [
                    'success_count' => $successCount,
                    'failed_stories' => $failedStories,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => "Successfully published {$results['success_count']} stories",
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk publish error', ['error' => $e->getMessage()]);
            
            return $this->errorResponse('Failed to perform bulk publish operation', 500);
        }
    }

    // Private helper methods
    private function clearStoryCache(int $storyId): void
    {
        $patterns = [
            "story_analytics_{$storyId}",
            "story_view_timeline_{$storyId}",
            "story_rating_trends_{$storyId}_*",
            'dashboard_overview',
            'content_performance_*',
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
        
        // Clear dashboard cache
        Cache::forget('dashboard_overview_' . now()->format('Y-m-d-H-i'));
    }
}
