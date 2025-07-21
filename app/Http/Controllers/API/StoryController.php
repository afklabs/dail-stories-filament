<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\StoryIndexRequest;
use App\Http\Requests\API\StoryRatingRequest;
use App\Http\Requests\ReadingProgressRequest;
use App\Http\Requests\RecordViewRequest;
use App\Http\Requests\StoryInteractionRequest;
use App\Models\Category;
use App\Models\MemberReadingHistory;
use App\Models\MemberStoryInteraction;
use App\Models\MemberStoryRating;
use App\Models\Story;
use App\Models\StoryRatingAggregate;
use App\Models\StoryView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;

class StoryController extends Controller
{
    /**
     * Cache TTL constants optimized for file cache
     */
    private const CACHE_SHORT = 300; // 5 minutes
    private const CACHE_MEDIUM = 900; // 15 minutes  
    private const CACHE_LONG = 3600; // 1 hour

    /**
     * Get all active stories with pagination
     * GET /v1/stories
     * Rate Limited: 60 requests per minute
     */
    public function index(StoryIndexRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $perPage = min($validated['per_page'] ?? 10, 50);
            $categoryId = $validated['category_id'] ?? null;
            $search = $validated['search'] ?? null;
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Build secure query with eager loading to prevent N+1
            $query = Story::query()
                ->where('active', true)
                ->where('active_from', '<=', now())
                ->where(function (Builder $q) {
                    $q->whereNull('active_until')
                      ->orWhere('active_until', '>', now());
                })
                ->with([
                    'category:id,name,slug',
                    'tags:id,name,slug',
                    'ratingAggregate:story_id,average_rating,total_ratings'
                ])
                ->select([
                    'id', 'title', 'excerpt', 'image', 'category_id',
                    'views', 'reading_time_minutes', 'active_from',
                    'created_at', 'updated_at'
                ]);

            // Secure category filter
            if ($categoryId) {
                $query->where('category_id', (int) $categoryId);
            }

            // Secure search with proper SQL escaping
            if ($search) {
                $searchTerm = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search));
                $query->where(function (Builder $q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('excerpt', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Secure sorting with whitelist
            $allowedSorts = ['created_at', 'views', 'title'];
            $allowedOrders = ['asc', 'desc'];
            
            if (in_array($sortBy, $allowedSorts) && in_array($sortOrder, $allowedOrders)) {
                if ($sortBy === 'title') {
                    $query->orderByRaw("LOWER(title) {$sortOrder}");
                } else {
                    $query->orderBy($sortBy, $sortOrder);
                }
            }

            // Paginate results
            $stories = $query->paginate($perPage);

            // Transform data for response
            $storyData = $stories->getCollection()->map(function ($story) {
                return [
                    'id' => $story->id,
                    'title' => $story->title,
                    'excerpt' => $story->excerpt,
                    'image' => $story->image ? asset('storage/' . $story->image) : null,
                    'category' => $story->category ? [
                        'id' => $story->category->id,
                        'name' => $story->category->name,
                        'slug' => $story->category->slug ?? null,
                    ] : null,
                    'tags' => $story->tags->map(fn($tag) => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug ?? null,
                    ]),
                    'views' => $story->views,
                    'reading_time_minutes' => $story->reading_time_minutes,
                    'rating' => [
                        'average' => round($story->ratingAggregate?->average_rating ?? 0, 1),
                        'total' => $story->ratingAggregate?->total_ratings ?? 0,
                    ],
                    'active_from' => $story->active_from?->toISOString(),
                    'created_at' => $story->created_at->toISOString(),
                ];
            });

            // Add member interactions if authenticated
            $memberId = auth('sanctum')->id();
            if ($memberId) {
                $this->addMemberInteractions($storyData, $memberId);
            }

            return response()->json([
                'success' => true,
                'data' => $storyData,
                'pagination' => [
                    'current_page' => $stories->currentPage(),
                    'per_page' => $stories->perPage(),
                    'total' => $stories->total(),
                    'last_page' => $stories->lastPage(),
                    'has_more' => $stories->hasMorePages(),
                ],
                'meta' => [
                    'total_active_stories' => $stories->total(),
                    'applied_filters' => array_filter([
                        'category_id' => $categoryId,
                        'search' => $search,
                        'sort_by' => $sortBy,
                        'sort_order' => $sortOrder,
                    ]),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Stories index error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load stories',
            ], 500);
        }
    }

    /**
     * Get single story details
     * GET /v1/stories/{id}
     * Rate Limited: 120 requests per minute
     */
    public function show(Request $request, Story $story): JsonResponse
    {
        try {
            // Check story availability
            if (!$story->active ||
                ($story->active_from && $story->active_from > now()) ||
                ($story->active_until && $story->active_until < now())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Story not available',
                ], 404);
            }

            // Load relationships efficiently
            $story->load([
                'category:id,name,slug',
                'tags:id,name,slug',
                'ratingAggregate:story_id,average_rating,total_ratings,rating_distribution'
            ]);

            // Build story response
            $storyData = [
                'id' => $story->id,
                'title' => $story->title,
                'content' => $story->content,
                'excerpt' => $story->excerpt,
                'image' => $story->image ? asset('storage/' . $story->image) : null,
                'category' => $story->category ? [
                    'id' => $story->category->id,
                    'name' => $story->category->name,
                    'slug' => $story->category->slug ?? null,
                ] : null,
                'tags' => $story->tags->map(fn($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug ?? null,
                ]),
                'views' => $story->views,
                'reading_time_minutes' => $story->reading_time_minutes,
                'rating' => [
                    'average' => round($story->ratingAggregate?->average_rating ?? 0, 1),
                    'total' => $story->ratingAggregate?->total_ratings ?? 0,
                    'distribution' => $story->ratingAggregate?->rating_distribution ?? [],
                ],
                'active_from' => $story->active_from?->toISOString(),
                'active_until' => $story->active_until?->toISOString(),
                'created_at' => $story->created_at->toISOString(),
                'updated_at' => $story->updated_at->toISOString(),
            ];

            // Add member-specific data if authenticated
            $memberId = auth('sanctum')->id();
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
     * Get featured stories
     * GET /v1/stories/featured
     * Rate Limited: 30 requests per minute
     */
    public function featured(Request $request): JsonResponse
    {
        try {
            $limit = min($request->integer('limit', 5), 20);

            $cacheKey = "featured_stories_{$limit}";
            $stories = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($limit) {
                return Story::query()
                    ->where('active', true)
                    ->where('active_from', '<=', now())
                    ->where(function (Builder $q) {
                        $q->whereNull('active_until')
                          ->orWhere('active_until', '>', now());
                    })
                    ->where(function (Builder $q) {
                        $q->where('is_featured', true)
                          ->orWhere('views', '>', 1000); // High view count as featured
                    })
                    ->with([
                        'category:id,name,slug',
                        'ratingAggregate:story_id,average_rating,total_ratings'
                    ])
                    ->select([
                        'id', 'title', 'excerpt', 'image', 'category_id',
                        'views', 'reading_time_minutes', 'created_at'
                    ])
                    ->orderByDesc('views')
                    ->limit($limit)
                    ->get();
            });

            $transformedStories = $stories->map(function ($story) {
                return [
                    'id' => $story->id,
                    'title' => $story->title,
                    'excerpt' => $story->excerpt,
                    'image' => $story->image ? asset('storage/' . $story->image) : null,
                    'category' => $story->category ? [
                        'id' => $story->category->id,
                        'name' => $story->category->name,
                    ] : null,
                    'views' => $story->views,
                    'reading_time_minutes' => $story->reading_time_minutes,
                    'rating' => [
                        'average' => round($story->ratingAggregate?->average_rating ?? 0, 1),
                        'total' => $story->ratingAggregate?->total_ratings ?? 0,
                    ],
                    'created_at' => $story->created_at->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedStories,
                'meta' => [
                    'count' => $transformedStories->count(),
                    'type' => 'featured',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Featured stories error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load featured stories',
            ], 500);
        }
    }

    /**
     * Get trending stories
     * GET /v1/stories/trending
     * Rate Limited: 30 requests per minute
     */
    public function trending(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'limit' => 'integer|min:1|max:20',
                'period' => 'string|in:24hours,7days,30days',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid parameters',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $limit = $request->integer('limit', 10);
            $period = $request->input('period', '7days');

            $days = match($period) {
                '24hours' => 1,
                '7days' => 7,
                '30days' => 30,
                default => 7,
            };

            $cacheKey = "trending_stories_{$period}_{$limit}";
            $stories = Cache::remember($cacheKey, self::CACHE_SHORT, function () use ($limit, $days) {
                return Story::query()
                    ->where('active', true)
                    ->where('active_from', '<=', now())
                    ->where('created_at', '>=', now()->subDays($days))
                    ->where(function (Builder $q) {
                        $q->whereNull('active_until')
                          ->orWhere('active_until', '>', now());
                    })
                    ->withCount(['storyViews as recent_views' => function ($query) use ($days) {
                        $query->where('viewed_at', '>=', now()->subDays($days));
                    }])
                    ->with([
                        'category:id,name,slug',
                        'ratingAggregate:story_id,average_rating,total_ratings'
                    ])
                    ->select([
                        'id', 'title', 'excerpt', 'image', 'category_id',
                        'views', 'reading_time_minutes', 'created_at'
                    ])
                    ->having('recent_views', '>', 0)
                    ->orderByDesc('recent_views')
                    ->limit($limit)
                    ->get();
            });

            $transformedStories = $stories->map(function ($story) {
                return [
                    'id' => $story->id,
                    'title' => $story->title,
                    'excerpt' => $story->excerpt,
                    'image' => $story->image ? asset('storage/' . $story->image) : null,
                    'category' => $story->category ? [
                        'id' => $story->category->id,
                        'name' => $story->category->name,
                    ] : null,
                    'views' => $story->views,
                    'recent_views' => $story->recent_views ?? 0,
                    'reading_time_minutes' => $story->reading_time_minutes,
                    'rating' => [
                        'average' => round($story->ratingAggregate?->average_rating ?? 0, 1),
                        'total' => $story->ratingAggregate?->total_ratings ?? 0,
                    ],
                    'created_at' => $story->created_at->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedStories,
                'meta' => [
                    'count' => $transformedStories->count(),
                    'period' => $period,
                    'type' => 'trending',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Trending stories error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load trending stories',
            ], 500);
        }
    }

    /**
     * Get story categories
     * GET /v1/stories/categories
     * Rate Limited: 20 requests per minute
     */
    public function categories(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'story_categories_with_counts';
            
            $categories = Cache::remember($cacheKey, self::CACHE_LONG, function () {
                return Category::query()
                    ->withCount(['stories as active_stories_count' => function ($query) {
                        $query->where('active', true)
                              ->where('active_from', '<=', now())
                              ->where(function (Builder $q) {
                                  $q->whereNull('active_until')
                                    ->orWhere('active_until', '>', now());
                              });
                    }])
                    ->having('active_stories_count', '>', 0)
                    ->orderBy('name')
                    ->get();
            });

            $transformedCategories = $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug ?? null,
                    'description' => $category->description ?? null,
                    'active_stories_count' => $category->active_stories_count,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedCategories,
                'meta' => [
                    'total_categories' => $transformedCategories->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Story categories error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load categories',
            ], 500);
        }
    }

    /**
     * Search stories with secure implementation
     * GET /v1/stories/search
     * Rate Limited: 30 requests per minute
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'q' => 'required|string|min:2|max:100',
                'category_id' => 'nullable|integer|exists:categories,id',
                'per_page' => 'integer|min:1|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid search parameters',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = trim($request->input('q'));
            $categoryId = $request->input('category_id');
            $perPage = min($request->integer('per_page', 10), 20);

            // Secure search term preparation
            $searchTerm = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

            $searchQuery = Story::query()
                ->where('active', true)
                ->where('active_from', '<=', now())
                ->where(function (Builder $q) {
                    $q->whereNull('active_until')
                      ->orWhere('active_until', '>', now());
                })
                ->where(function (Builder $q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('excerpt', 'LIKE', "%{$searchTerm}%");
                });

            if ($categoryId) {
                $searchQuery->where('category_id', (int) $categoryId);
            }

            $results = $searchQuery
                ->with([
                    'category:id,name,slug',
                    'ratingAggregate:story_id,average_rating,total_ratings'
                ])
                ->select([
                    'id', 'title', 'excerpt', 'image', 'category_id',
                    'views', 'reading_time_minutes', 'created_at'
                ])
                ->orderByDesc('views')
                ->paginate($perPage);

            $transformedStories = $results->getCollection()->map(function ($story) {
                return [
                    'id' => $story->id,
                    'title' => $story->title,
                    'excerpt' => $story->excerpt,
                    'image' => $story->image ? asset('storage/' . $story->image) : null,
                    'category' => $story->category ? [
                        'id' => $story->category->id,
                        'name' => $story->category->name,
                    ] : null,
                    'views' => $story->views,
                    'reading_time_minutes' => $story->reading_time_minutes,
                    'rating' => [
                        'average' => round($story->ratingAggregate?->average_rating ?? 0, 1),
                        'total' => $story->ratingAggregate?->total_ratings ?? 0,
                    ],
                    'created_at' => $story->created_at->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedStories,
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                    'has_more' => $results->hasMorePages(),
                ],
                'meta' => [
                    'query' => $query,
                    'category_id' => $categoryId,
                    'total_results' => $results->total(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Story search error', [
                'query' => $request->input('q'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Search failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Record story view with comprehensive analytics tracking
     * POST /v1/stories/{id}/view
     * Rate Limited: 60 requests per minute
     */
    public function recordView(RecordViewRequest $request, Story $story): JsonResponse
    {
        try {
            $validated = $request->validated();
            $memberId = auth('sanctum')->id();
            $deviceId = $request->header('X-Device-ID');
            $userAgent = $request->header('User-Agent');
            $referrer = $request->header('Referer');
            $ipAddress = $request->ip();

            // Check for duplicate views (30 minute window)
            $duplicateCheck = StoryView::where('story_id', $story->id)
                ->where(function ($query) use ($memberId, $deviceId, $ipAddress) {
                    if ($memberId) {
                        $query->where('member_id', $memberId);
                    } elseif ($deviceId) {
                        $query->where('device_id', $deviceId);
                    } else {
                        $query->where('ip_address', $ipAddress);
                    }
                })
                ->where('viewed_at', '>', now()->subMinutes(30))
                ->exists();

            if (!$duplicateCheck) {
                // Create comprehensive view record
                StoryView::create([
                    'story_id' => $story->id,
                    'member_id' => $memberId,
                    'device_id' => $deviceId,
                    'session_id' => session()->getId(),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'referrer' => $referrer,
                    'viewed_at' => now(),
                    'metadata' => [
                        'reading_time' => $validated['reading_time'] ?? null,
                        'scroll_percentage' => $validated['scroll_percentage'] ?? null,
                        'platform' => $this->detectPlatform($userAgent),
                        'browser' => $this->detectBrowser($userAgent),
                        'is_mobile' => $this->isMobile($userAgent),
                        'screen_resolution' => $request->header('X-Screen-Resolution'),
                        'timezone' => $request->header('X-Timezone', 'UTC'),
                        'language' => $request->header('Accept-Language'),
                        'referrer_domain' => $referrer ? parse_url($referrer, PHP_URL_HOST) : null,
                    ],
                ]);

                // Increment story views
                $story->increment('views');

                // Create member interaction if authenticated
                if ($memberId) {
                    MemberStoryInteraction::firstOrCreate([
                        'member_id' => $memberId,
                        'story_id' => $story->id,
                        'action' => 'view',
                    ], [
                        'metadata' => [
                            'first_viewed_at' => now()->toISOString(),
                            'device_id' => $deviceId,
                            'platform' => $this->detectPlatform($userAgent),
                        ]
                    ]);
                }

                $message = 'View recorded successfully';
                $isNewView = true;
            } else {
                $message = 'View already recorded recently';
                $isNewView = false;
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'story_id' => $story->id,
                    'total_views' => $story->fresh()->views, // Get fresh count
                    'is_new_view' => $isNewView,
                    'recorded_at' => now()->toISOString(),
                    'analytics_captured' => [
                        'device_tracking' => !empty($deviceId),
                        'member_tracking' => !empty($memberId),
                        'session_tracking' => true,
                        'referrer_tracking' => !empty($referrer),
                        'metadata_captured' => !empty($validated),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Record view error', [
                'story_id' => $story->id,
                'member_id' => auth('sanctum')->id(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record view',
            ], 500);
        }
    }

    /**
     * Submit story rating
     * POST /v1/stories/{id}/rate
     * Rate Limited: 20 requests per minute
     */
    public function submitRating(StoryRatingRequest $request, Story $story): JsonResponse
    {
        try {
            $validated = $request->validated();
            $memberId = auth('sanctum')->id();

            DB::transaction(function () use ($validated, $story, $memberId) {
                MemberStoryRating::updateOrCreate(
                    [
                        'member_id' => $memberId,
                        'story_id' => $story->id,
                    ],
                    [
                        'rating' => (int) $validated['rating'],
                        'comment' => !empty($validated['comment']) ? trim($validated['comment']) : null,
                    ]
                );

                $this->updateRatingAggregate($story->id);
            });

            return response()->json([
                'success' => true,
                'message' => 'Rating submitted successfully',
                'data' => [
                    'rating' => (int) $validated['rating'],
                    'comment' => $validated['comment'] ?? null,
                    'submitted_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Submit rating error', [
                'story_id' => $story->id,
                'member_id' => auth('sanctum')->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit rating',
            ], 500);
        }
    }

    /**
     * Get reading progress
     * GET /v1/stories/{id}/progress
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
                    'story_id' => $story->id,
                    'reading_progress' => $progress?->reading_progress ?? 0,
                    'time_spent' => $progress?->time_spent ?? 0,
                    'last_read_at' => $progress?->last_read_at?->toISOString(),
                    'is_completed' => ($progress?->reading_progress ?? 0) >= 100,
                    'status' => $this->getProgressStatus($progress?->reading_progress ?? 0),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get reading progress error', [
                'story_id' => $story->id,
                'member_id' => auth('sanctum')->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load reading progress',
            ], 500);
        }
    }

    /**
     * Update reading progress
     * POST /v1/stories/{id}/progress
     * Rate Limited: 60 requests per minute
     */
    public function updateReadingProgress(ReadingProgressRequest $request, Story $story): JsonResponse
    {
        try {
            $validated = $request->validated();
            $memberId = auth('sanctum')->id();

            $progress = max(0, min(100, (float) $validated['progress']));
            $timeSpent = max(0, (int) ($validated['time_spent'] ?? 0));

            $progressRecord = MemberReadingHistory::updateOrCreate(
                [
                    'member_id' => $memberId,
                    'story_id' => $story->id,
                ],
                [
                    'reading_progress' => $progress,
                    'time_spent' => $timeSpent,
                    'last_read_at' => now(),
                ]
            );

            // Create view interaction if progress > 0
            if ($progress > 0) {
                MemberStoryInteraction::firstOrCreate([
                    'member_id' => $memberId,
                    'story_id' => $story->id,
                    'action' => 'view',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Reading progress updated successfully',
                'data' => [
                    'reading_progress' => $progressRecord->reading_progress,
                    'time_spent' => $progressRecord->time_spent,
                    'is_completed' => $progressRecord->reading_progress >= 100,
                    'status' => $this->getProgressStatus($progressRecord->reading_progress),
                    'updated_at' => $progressRecord->updated_at->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Update reading progress error', [
                'story_id' => $story->id,
                'member_id' => auth('sanctum')->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update reading progress',
            ], 500);
        }
    }

    /**
     * Get story analytics for admin dashboard
     * GET /v1/stories/{id}/analytics
     * Rate Limited: 10 requests per minute
     */
    public function getAnalytics(Request $request, Story $story): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'period' => 'string|in:7days,30days,90days',
                'include_detailed' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid parameters',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $period = $request->input('period', '30days');
            $includeDetailed = $request->boolean('include_detailed', false);

            $days = match($period) {
                '7days' => 7,
                '30days' => 30,
                '90days' => 90,
                default => 30,
            };

            $startDate = now()->subDays($days);
            $cacheKey = "story_analytics_{$story->id}_{$period}_" . ($includeDetailed ? 'detailed' : 'basic');

            $analytics = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($story, $startDate, $includeDetailed) {
                $baseData = [
                    'total_views' => $story->views,
                    'unique_views' => $story->storyViews()->distinct(['device_id', 'member_id'])->count(),
                    'member_views' => $story->storyViews()->whereNotNull('member_id')->count(),
                    'guest_views' => $story->storyViews()->whereNull('member_id')->count(),
                    'total_interactions' => $story->interactions()->count(),
                    'total_ratings' => $story->ratingAggregate?->total_ratings ?? 0,
                    'average_rating' => round($story->ratingAggregate?->average_rating ?? 0, 1),
                    'completion_rate' => $this->calculateCompletionRate($story->id),
                ];

                if ($includeDetailed) {
                    $baseData['detailed'] = [
                        'recent_views' => $story->storyViews()->where('viewed_at', '>=', $startDate)->count(),
                        'hourly_distribution' => $this->getHourlyDistribution($story, $startDate),
                        'platform_breakdown' => $this->getPlatformBreakdown($story, $startDate),
                        'engagement_metrics' => [
                            'bookmark_count' => $story->interactions()->where('action', 'bookmark')->count(),
                            'share_count' => $story->interactions()->where('action', 'share')->count(),
                            'like_count' => $story->interactions()->where('action', 'like')->count(),
                        ],
                    ];
                }

                return $baseData;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'story_id' => $story->id,
                    'story_title' => $story->title,
                    'period' => $period,
                    'analytics' => $analytics,
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Story analytics error', [
                'story_id' => $story->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load analytics',
            ], 500);
        }
    }

    // ===== PRIVATE HELPER METHODS =====

    /**
     * Add member interactions to story collection
     */
    private function addMemberInteractions($stories, int $memberId): void
    {
        if ($stories->isEmpty()) return;

        $storyIds = $stories->pluck('id')->toArray();

        // Get member ratings efficiently
        $ratings = MemberStoryRating::where('member_id', $memberId)
            ->whereIn('story_id', $storyIds)
            ->pluck('rating', 'story_id');

        // Get member interactions efficiently
        $interactions = MemberStoryInteraction::where('member_id', $memberId)
            ->whereIn('story_id', $storyIds)
            ->get()
            ->groupBy('story_id')
            ->map(fn($group) => $group->pluck('action')->toArray());

        // Add interaction data to each story
        $stories->transform(function ($story) use ($ratings, $interactions) {
            $storyInteractions = $interactions[$story['id']] ?? [];
            
            $story['member_interactions'] = [
                'has_rated' => $ratings->has($story['id']),
                'rating' => $ratings->get($story['id']),
                'has_bookmarked' => in_array('bookmark', $storyInteractions),
                'has_liked' => in_array('like', $storyInteractions),
                'has_shared' => in_array('share', $storyInteractions),
                'has_viewed' => in_array('view', $storyInteractions),
            ];

            return $story;
        });
    }

    /**
     * Get member-specific story data
     */
    private function getMemberStoryData(int $storyId, int $memberId): array
    {
        // Use single query to get all member data at once
        $memberData = collect([
            'interactions' => MemberStoryInteraction::where('member_id', $memberId)
                ->where('story_id', $storyId)
                ->pluck('action')
                ->toArray(),
            'rating' => MemberStoryRating::where('member_id', $memberId)
                ->where('story_id', $storyId)
                ->first(),
            'reading_history' => MemberReadingHistory::where('member_id', $memberId)
                ->where('story_id', $storyId)
                ->first(),
        ]);

        return [
            'interactions' => [
                'has_viewed' => in_array('view', $memberData['interactions']),
                'has_bookmarked' => in_array('bookmark', $memberData['interactions']),
                'has_liked' => in_array('like', $memberData['interactions']),
                'has_shared' => in_array('share', $memberData['interactions']),
            ],
            'rating' => [
                'has_rated' => $memberData['rating'] !== null,
                'rating' => $memberData['rating']?->rating,
                'comment' => $memberData['rating']?->comment,
                'rated_at' => $memberData['rating']?->created_at?->toISOString(),
            ],
            'reading_progress' => [
                'progress' => $memberData['reading_history']?->reading_progress ?? 0,
                'time_spent' => $memberData['reading_history']?->time_spent ?? 0,
                'last_read_at' => $memberData['reading_history']?->last_read_at?->toISOString(),
                'is_completed' => ($memberData['reading_history']?->reading_progress ?? 0) >= 100,
                'status' => $this->getProgressStatus($memberData['reading_history']?->reading_progress ?? 0),
            ],
        ];
    }

    /**
     * Get reading progress status
     */
    private function getProgressStatus(float $progress): string
    {
        return match (true) {
            $progress === 0.0 => 'not_started',
            $progress > 0 && $progress < 10 => 'just_started',
            $progress >= 10 && $progress < 90 => 'in_progress',
            $progress >= 90 && $progress < 100 => 'almost_done',
            $progress >= 100 => 'completed',
            default => 'unknown',
        };
    }

    /**
     * Calculate story completion rate
     */
    private function calculateCompletionRate(int $storyId): float
    {
        $totalReads = MemberReadingHistory::where('story_id', $storyId)->count();
        if ($totalReads === 0) return 0.0;

        $completedReads = MemberReadingHistory::where('story_id', $storyId)
            ->where('reading_progress', '>=', 100)
            ->count();

        return round(($completedReads / $totalReads) * 100, 2);
    }

    /**
     * User agent detection methods
     */
    private function detectPlatform(?string $userAgent): string
    {
        if (!$userAgent) return 'Unknown';
        
        return match (true) {
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown',
        };
    }

    private function detectBrowser(?string $userAgent): string
    {
        if (!$userAgent) return 'Unknown';
        
        return match (true) {
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Safari') && !str_contains($userAgent, 'Chrome') => 'Safari',
            str_contains($userAgent, 'Edge') => 'Edge',
            str_contains($userAgent, 'Opera') => 'Opera',
            default => 'Unknown',
        };
    }

    private function isMobile(?string $userAgent): bool
    {
        if (!$userAgent) return false;
        return (bool) preg_match('/(Mobile|Android|iPhone|iPad|iPod|BlackBerry|Windows Phone)/i', $userAgent);
    }

    /**
     * Get hourly view distribution
     */
    private function getHourlyDistribution(Story $story, $startDate): array
    {
        return $story->storyViews()
            ->where('viewed_at', '>=', $startDate)
            ->selectRaw('HOUR(viewed_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();
    }

    /**
     * Get platform breakdown
     */
    private function getPlatformBreakdown(Story $story, $startDate): array
    {
        $views = $story->storyViews()
            ->where('viewed_at', '>=', $startDate)
            ->whereNotNull('user_agent')
            ->get();

        $platforms = ['iOS' => 0, 'Android' => 0, 'Windows' => 0, 'macOS' => 0, 'Linux' => 0, 'Unknown' => 0];

        foreach ($views as $view) {
            $platform = $this->detectPlatform($view->user_agent);
            $platforms[$platform] = ($platforms[$platform] ?? 0) + 1;
        }

        return $platforms;
    }

    /**
     * Update story rating aggregate
     */
    private function updateRatingAggregate(int $storyId): void
    {
        $ratings = MemberStoryRating::where('story_id', $storyId)->get();

        if ($ratings->isEmpty()) {
            StoryRatingAggregate::where('story_id', $storyId)->delete();
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

        // Clear story-specific caches
        Cache::forget("story_analytics_{$storyId}_30days_basic");
        Cache::forget("story_analytics_{$storyId}_30days_detailed");
        Cache::forget("featured_stories_5");
    }
}