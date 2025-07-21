<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\StoryIndexRequest;
use App\Http\Requests\API\StoryRatingRequest;
use App\Http\Requests\ReadingProgressRequest;
use App\Http\Requests\RecordViewRequest;
use App\Http\Requests\StoryInteractionRequest;
use App\Models\MemberReadingHistory;
use App\Models\MemberStoryInteraction;
use App\Models\MemberStoryRating;
use App\Models\Story;
use App\Models\StoryRatingAggregate;
use App\Models\StoryView;
use App\Services\SecureQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoryController extends Controller
{
    /**
     * Get all active stories with pagination
     */
    public function index(StoryIndexRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $perPage = $validated['per_page'] ?? 10;
            $categoryId = $validated['category_id'] ?? null;
            $search = $validated['search'] ?? null;
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Headers for tracking
            $deviceId = $request->header('X-Device-ID');
            $memberId = auth('sanctum')->id();

            // Build query with security measures
            $query = Story::query()
                ->where('active', true)
                ->where('active_from', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('active_until')
                        ->orWhere('active_until', '>', now());
                })
                ->select([
                    'id',
                    'title',
                    'content',
                    'excerpt',
                    'image',
                    'category_id',
                    'views',
                    'reading_time_minutes',
                    'active_from',
                    'active_until',
                    'created_at',
                    'updated_at',
                ])
                ->with([
                    'category:id,name',
                    'tags:id,name',
                ]);

            // Apply filters
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if ($search) {
                $query = SecureQueryBuilder::applySearch($query, $search, ['title', 'excerpt']);
            }

            // Apply sorting
            $query = SecureQueryBuilder::applySorting($query, $sortBy, $sortOrder);

            // Paginate
            $stories = $query->paginate($perPage);

            // Add member-specific data if authenticated
            if ($memberId) {
                $this->addMemberInteractions($stories->items(), $memberId);
            }

            return response()->json([
                'success' => true,
                'data' => $stories->items(),
                'pagination' => [
                    'current_page' => $stories->currentPage(),
                    'per_page' => $stories->perPage(),
                    'total' => $stories->total(),
                    'last_page' => $stories->lastPage(),
                    'has_more' => $stories->hasMorePages(),
                ],
                'meta' => [
                    'total_count' => $stories->total(),
                    'active_stories' => Story::where('active', true)->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Stories index error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load stories',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get single story details
     */
    public function show(Request $request, Story $story): JsonResponse
    {
        try {
            // Check if story is active
            if (! $story->active ||
                ($story->active_from && $story->active_from > now()) ||
                ($story->active_until && $story->active_until < now())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Story not available',
                ], 404);
            }

            $memberId = auth('sanctum')->id();

            // Load relationships
            $story->load([
                'category:id,name',
                'tags:id,name',
                'ratingAggregate',
            ]);

            // Add member-specific data
            $storyData = $story->toArray();
            if ($memberId) {
                $storyData['member_data'] = $this->getMemberStoryData($story->id, $memberId);
            }

            return response()->json([
                'success' => true,
                'data' => $storyData,
            ]);
        } catch (\Exception $e) {
            Log::error('Story show error', [
                'story_id' => $story->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load story',
            ], 500);
        }
    }

    /**
     * Get story statistics
     */
    public function getStats(Story $story): JsonResponse
    {
        try {
            $cacheKey = "story_stats_{$story->id}";

            $stats = Cache::remember($cacheKey, 300, function () use ($story) {
                return [
                    'views' => $story->views ?? 0,
                    'total_ratings' => $story->ratingAggregate?->total_ratings ?? 0,
                    'average_rating' => round($story->ratingAggregate?->average_rating ?? 0, 1),
                    'interactions' => [
                        'likes' => MemberStoryInteraction::where('story_id', $story->id)
                            ->where('action', 'like')->count(),
                        'bookmarks' => MemberStoryInteraction::where('story_id', $story->id)
                            ->where('action', 'bookmark')->count(),
                        'shares' => MemberStoryInteraction::where('story_id', $story->id)
                            ->where('action', 'share')->count(),
                    ],
                    'completion_rate' => $this->getCompletionRate($story->id),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Story stats error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics',
            ], 500);
        }
    }

    /**
     * Record story view
     */
    public function recordView(RecordViewRequest $request, Story $story): JsonResponse
    {
        try {
            $validated = $request->validated();
            $deviceId = $request->header('X-Device-ID');
            $memberId = auth('sanctum')->id();

            // Check for existing view (to avoid duplicates)
            $existingView = StoryView::where('story_id', $story->id)
                ->where(function ($query) use ($memberId, $deviceId) {
                    if ($memberId) {
                        $query->where('member_id', $memberId);
                    } elseif ($deviceId) {
                        $query->where('device_id', $deviceId);
                    } else {
                        $query->where('ip_address', request()->ip());
                    }
                })
                ->where('viewed_at', '>', now()->subHours(24))
                ->first();

            if (! $existingView) {
                // Create new view record
                StoryView::create([
                    'story_id' => $story->id,
                    'member_id' => $memberId,
                    'device_id' => $deviceId,
                    'session_id' => session()->getId(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'viewed_at' => now(),
                    'metadata' => [
                        'reading_time' => $validated['reading_time'] ?? null,
                        'scroll_percentage' => $validated['scroll_percentage'] ?? null,
                    ],
                ]);

                // Increment story views counter
                $story->increment('views');

                // Create interaction record if member is authenticated
                if ($memberId) {
                    MemberStoryInteraction::firstOrCreate([
                        'member_id' => $memberId,
                        'story_id' => $story->id,
                        'action' => 'view',
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'View recorded successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Record view error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record view',
            ], 500);
        }
    }

    /**
     * Get story rating
     */
    public function getRating(Story $story): JsonResponse
    {
        try {
            $data = [
                'total_ratings' => $story->ratingAggregate?->total_ratings ?? 0,
                'average_rating' => round($story->ratingAggregate?->average_rating ?? 0, 1),
                'rating_distribution' => $story->ratingAggregate?->rating_distribution ?? [],
            ];

            // Add member's rating if authenticated
            if (auth('sanctum')->check()) {
                $memberRating = MemberStoryRating::where([
                    'member_id' => auth('sanctum')->id(),
                    'story_id' => $story->id,
                ])->first();

                $data['member_rating'] = $memberRating ? [
                    'rating' => $memberRating->rating,
                    'comment' => $memberRating->comment,
                    'created_at' => $memberRating->created_at,
                ] : null;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Get rating error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load rating',
            ], 500);
        }
    }

    /**
     * Submit story rating
     */
    public function submitRating(StoryRatingRequest $request, Story $story): JsonResponse
    {
        try {
            $validated = $request->validated();
            $memberId = auth('sanctum')->id();

            DB::transaction(function () use ($validated, $story, $memberId) {
                // Create or update rating
                $rating = MemberStoryRating::updateOrCreate(
                    [
                        'member_id' => $memberId,
                        'story_id' => $story->id,
                    ],
                    [
                        'rating' => $validated['rating'],
                        'comment' => $validated['comment'] ?? null,
                    ]
                );

                // Update aggregate (this should be handled by model events, but ensuring it here)
                $this->updateRatingAggregate($story->id);
            });

            return response()->json([
                'success' => true,
                'message' => 'Rating submitted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Submit rating error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit rating',
            ], 500);
        }
    }

    /**
     * Record story interaction
     */
    public function recordInteraction(StoryInteractionRequest $request, Story $story): JsonResponse
    {
        try {
            $validated = $request->validated();
            $memberId = auth('sanctum')->id();

            $interaction = MemberStoryInteraction::updateOrCreate(
                [
                    'member_id' => $memberId,
                    'story_id' => $story->id,
                    'action' => $validated['action'],
                ],
                [
                    'metadata' => $validated['metadata'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Interaction recorded successfully',
                'data' => [
                    'action' => $interaction->action,
                    'created_at' => $interaction->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Record interaction error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record interaction',
            ], 500);
        }
    }

    /**
     * Remove story interaction
     */
    public function removeInteraction(Request $request, Story $story): JsonResponse
    {
        try {
            $action = $request->input('action');
            $memberId = auth('sanctum')->id();

            $deleted = MemberStoryInteraction::where([
                'member_id' => $memberId,
                'story_id' => $story->id,
                'action' => $action,
            ])->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Interaction removed successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Interaction not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Remove interaction error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove interaction',
            ], 500);
        }
    }

    /**
     * Get reading progress
     */
    public function getReadingProgress(Story $story): JsonResponse
    {
        try {
            $memberId = auth('sanctum')->id();

            $progress = MemberReadingHistory::where([
                'member_id' => $memberId,
                'story_id' => $story->id,
            ])->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'reading_progress' => $progress?->reading_progress ?? 0,
                    'time_spent' => $progress?->time_spent ?? 0,
                    'last_read_at' => $progress?->last_read_at,
                    'is_completed' => ($progress?->reading_progress ?? 0) >= 100,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get reading progress error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load reading progress',
            ], 500);
        }
    }

    /**
     * Update reading progress
     */
    public function updateReadingProgress(ReadingProgressRequest $request, Story $story): JsonResponse
    {
        try {
            $validated = $request->validated();
            $memberId = auth('sanctum')->id();

            $progress = MemberReadingHistory::updateOrCreate(
                [
                    'member_id' => $memberId,
                    'story_id' => $story->id,
                ],
                [
                    'reading_progress' => $validated['progress'],
                    'time_spent' => $validated['time_spent'] ?? 0,
                    'last_read_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Reading progress updated successfully',
                'data' => [
                    'reading_progress' => $progress->reading_progress,
                    'is_completed' => $progress->reading_progress >= 100,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Update reading progress error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update reading progress',
            ], 500);
        }
    }

    // Private helper methods
    private function addMemberInteractions($stories, int $memberId): void
    {
        $storyIds = collect($stories)->pluck('id')->toArray();

        // Get member ratings
        $ratings = MemberStoryRating::where('member_id', $memberId)
            ->whereIn('story_id', $storyIds)
            ->pluck('rating', 'story_id')
            ->toArray();

        // Get member interactions
        $interactions = MemberStoryInteraction::where('member_id', $memberId)
            ->whereIn('story_id', $storyIds)
            ->get()
            ->groupBy('story_id')
            ->map(function ($group) {
                return $group->pluck('action')->toArray();
            })
            ->toArray();

        // Add to each story
        foreach ($stories as $story) {
            $storyInteractions = $interactions[$story->id] ?? [];

            $story->member_interactions = [
                'has_rated' => isset($ratings[$story->id]),
                'rating' => $ratings[$story->id] ?? null,
                'has_bookmarked' => in_array('bookmark', $storyInteractions),
                'has_liked' => in_array('like', $storyInteractions),
                'has_shared' => in_array('share', $storyInteractions),
            ];
        }
    }

    private function getMemberStoryData(int $storyId, int $memberId): array
    {
        $interactions = MemberStoryInteraction::where([
            'member_id' => $memberId,
            'story_id' => $storyId,
        ])->pluck('action')->toArray();

        $rating = MemberStoryRating::where([
            'member_id' => $memberId,
            'story_id' => $storyId,
        ])->first();

        $readingHistory = MemberReadingHistory::where([
            'member_id' => $memberId,
            'story_id' => $storyId,
        ])->first();

        return [
            'has_viewed' => in_array('view', $interactions),
            'has_rated' => $rating !== null,
            'rating' => $rating?->rating,
            'rating_comment' => $rating?->comment,
            'has_bookmarked' => in_array('bookmark', $interactions),
            'has_liked' => in_array('like', $interactions),
            'has_shared' => in_array('share', $interactions),
            'reading_progress' => $readingHistory?->reading_progress ?? 0,
            'time_spent' => $readingHistory?->time_spent ?? 0,
            'last_read_at' => $readingHistory?->last_read_at,
            'is_completed' => ($readingHistory?->reading_progress ?? 0) >= 100,
        ];
    }

    private function getCompletionRate(int $storyId): float
    {
        $totalViews = StoryView::where('story_id', $storyId)->count();
        $completedReads = MemberReadingHistory::where('story_id', $storyId)
            ->where('reading_progress', '>=', 100)
            ->count();

        return $totalViews > 0 ? round(($completedReads / $totalViews) * 100, 1) : 0;
    }

    private function updateRatingAggregate(int $storyId): void
    {
        $ratings = MemberStoryRating::where('story_id', $storyId)->get();

        if ($ratings->isEmpty()) {
            return;
        }

        $totalRatings = $ratings->count();
        $sumRatings = $ratings->sum('rating');
        $averageRating = $sumRatings / $totalRatings;

        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = $ratings->where('rating', $i)->count();
        }

        StoryRatingAggregate::updateOrCreate(
            ['story_id' => $storyId],
            [
                'total_ratings' => $totalRatings,
                'sum_ratings' => $sumRatings,
                'average_rating' => round($averageRating, 2),
                'rating_distribution' => $distribution,
                'comments_count' => $ratings->whereNotNull('comment')->count(),
                'last_rated_at' => $ratings->max('created_at'),
            ]
        );
    }
}
