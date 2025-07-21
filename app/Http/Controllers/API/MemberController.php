<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberProfileUpdateRequest;
use App\Models\Member;
use App\Models\MemberReadingHistory;
use App\Models\MemberStoryInteraction;
use App\Models\MemberStoryRating;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MemberController extends Controller
{
    /**
     * Register new member
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|min:2',
                'email' => 'required|string|email|max:255|unique:members',
                'password' => 'required|string|min:8|confirmed',
                'device_id' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'date_of_birth' => 'nullable|date|before:today',
                'gender' => 'nullable|string|in:male,female',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            $member = null;
            $token = null;

            DB::transaction(function () use ($validated, &$member, &$token) {
                $member = Member::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'device_id' => $validated['device_id'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'status' => 'active',
                    'last_login_at' => now(),
                ]);

                // Create API token
                $token = $member->createToken('api-token')->plainTextToken;
            });

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'member' => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'phone' => $member->phone,
                        'avatar' => $member->avatar,
                        'status' => $member->status,
                        'created_at' => $member->created_at,
                    ],
                    'token' => $token,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Member registration error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Login member
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
                'device_id' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            $member = Member::where('email', $validated['email'])->first();

            if (!$member || !Hash::check($validated['password'], $member->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            if ($member->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is not active',
                ], 403);
            }

            // Update login info
            $member->update([
                'last_login_at' => now(),
                'device_id' => $validated['device_id'] ?? $member->device_id,
            ]);

            // Revoke old tokens and create new one
            $member->tokens()->delete();
            $token = $member->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'member' => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'phone' => $member->phone,
                        'avatar' => $member->avatar,
                        'status' => $member->status,
                        'date_of_birth' => $member->date_of_birth,
                        'gender' => $member->gender,
                        'last_login_at' => $member->last_login_at,
                    ],
                    'token' => $token,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Member login error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Login failed',
            ], 500);
        }
    }

    /**
     * Logout member
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout successful',
            ]);
        } catch (\Exception $e) {
            Log::error('Member logout error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
            ], 500);
        }
    }

    /**
     * Get member profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $member = $request->user();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'avatar' => $member->avatar,
                    'date_of_birth' => $member->date_of_birth,
                    'gender' => $member->gender,
                    'status' => $member->status,
                    'last_login_at' => $member->last_login_at,
                    'created_at' => $member->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get member profile error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load profile',
            ], 500);
        }
    }

    /**
     * Update member profile
     */
    public function updateProfile(MemberProfileUpdateRequest $request): JsonResponse
    {
        try {
            $member = $request->user();
            $validated = $request->validated();

            // Handle password update if provided
            if (!empty($validated['new_password'])) {
                if (!Hash::check($validated['current_password'], $member->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect',
                    ], 422);
                }
                $validated['password'] = Hash::make($validated['new_password']);
                unset($validated['current_password'], $validated['new_password']);
            }

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $validated['avatar'] = $avatarPath;
            }

            // Update member
            $member->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'avatar' => $member->avatar,
                    'date_of_birth' => $member->date_of_birth,
                    'gender' => $member->gender,
                    'updated_at' => $member->updated_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Update member profile error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
            ], 500);
        }
    }

    /**
     * Get member bookmarks
     */
    public function bookmarks(Request $request): JsonResponse
    {
        try {
            $member = $request->user();
            $perPage = $request->integer('per_page', 10);

            $bookmarks = Story::whereHas('interactions', function ($query) use ($member) {
                $query->where('member_id', $member->id)
                    ->where('action', 'bookmark');
            })
                ->where('active', true)
                ->with(['category:id,name', 'ratingAggregate'])
                ->select(['id', 'title', 'excerpt', 'image', 'category_id', 'reading_time_minutes', 'created_at'])
                ->orderByDesc('created_at')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $bookmarks->items(),
                'pagination' => [
                    'current_page' => $bookmarks->currentPage(),
                    'per_page' => $bookmarks->perPage(),
                    'total' => $bookmarks->total(),
                    'last_page' => $bookmarks->lastPage(),
                    'has_more' => $bookmarks->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get member bookmarks error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load bookmarks',
            ], 500);
        }
    }

    /**
     * Get member rated stories
     */
    public function ratedStories(Request $request): JsonResponse
    {
        try {
            $member = $request->user();
            $perPage = $request->integer('per_page', 10);

            $ratedStories = Story::whereHas('ratings', function ($query) use ($member) {
                $query->where('member_id', $member->id);
            })
                ->with([
                    'category:id,name',
                    'ratingAggregate',
                    'ratings' => function ($query) use ($member) {
                        $query->where('member_id', $member->id);
                    },
                ])
                ->select(['id', 'title', 'excerpt', 'image', 'category_id', 'reading_time_minutes', 'created_at'])
                ->orderByDesc('created_at')
                ->paginate($perPage);

            // Add member rating to each story
            $ratedStories->getCollection()->transform(function ($story) {
                $memberRating = $story->ratings->first();
                if ($memberRating) {
                    $story->member_rating = [
                        'rating' => $memberRating->rating,
                        'comment' => $memberRating->comment,
                        'created_at' => $memberRating->created_at,
                    ];
                }
                unset($story->ratings); // Remove the relationship to clean response

                return $story;
            });

            return response()->json([
                'success' => true,
                'data' => $ratedStories->items(),
                'pagination' => [
                    'current_page' => $ratedStories->currentPage(),
                    'per_page' => $ratedStories->perPage(),
                    'total' => $ratedStories->total(),
                    'last_page' => $ratedStories->lastPage(),
                    'has_more' => $ratedStories->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get member rated stories error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load rated stories',
            ], 500);
        }
    }

    /**
     * Get member reading history
     */
    public function readingHistory(Request $request): JsonResponse
    {
        try {
            $member = $request->user();
            $perPage = $request->integer('per_page', 10);

            $readingHistory = MemberReadingHistory::where('member_id', $member->id)
                ->with([
                    'story:id,title,excerpt,image,category_id,reading_time_minutes',
                    'story.category:id,name',
                ])
                ->orderByDesc('last_read_at')
                ->paginate($perPage);

            $history = $readingHistory->getCollection()->map(function ($item) {
                return [
                    'story' => $item->story,
                    'reading_progress' => $item->reading_progress,
                    'time_spent' => $item->time_spent,
                    'last_read_at' => $item->last_read_at,
                    'is_completed' => $item->reading_progress >= 100,
                    'progress_status' => $this->getProgressStatus($item->reading_progress),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $history,
                'pagination' => [
                    'current_page' => $readingHistory->currentPage(),
                    'per_page' => $readingHistory->perPage(),
                    'total' => $readingHistory->total(),
                    'last_page' => $readingHistory->lastPage(),
                    'has_more' => $readingHistory->hasMorePages(),
                ],
                'summary' => [
                    'completed_stories' => MemberReadingHistory::where('member_id', $member->id)
                        ->where('reading_progress', '>=', 100)->count(),
                    'in_progress_stories' => MemberReadingHistory::where('member_id', $member->id)
                        ->where('reading_progress', '>', 0)
                        ->where('reading_progress', '<', 100)->count(),
                    'total_reading_time' => MemberReadingHistory::where('member_id', $member->id)
                        ->sum('time_spent'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get member reading history error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load reading history',
            ], 500);
        }
    }

    /**
     * Get member recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        try {
            $member = $request->user();
            $limit = $request->integer('limit', 5);

            // Simple recommendation algorithm based on:
            // 1. Categories member has read and rated highly
            // 2. Stories similar to highly rated ones
            // 3. Popular stories member hasn't read yet

            $recommendations = $this->generateRecommendations($member, $limit);

            return response()->json([
                'success' => true,
                'data' => $recommendations,
                'algorithm' => 'Based on your reading history and preferences',
            ]);
        } catch (\Exception $e) {
            Log::error('Get member recommendations error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load recommendations',
            ], 500);
        }
    }

    /**
     * Get member statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $member = $request->user();

            $stats = [
                'reading_stats' => [
                    'total_stories_read' => MemberReadingHistory::where('member_id', $member->id)->count(),
                    'completed_stories' => MemberReadingHistory::where('member_id', $member->id)
                        ->where('reading_progress', '>=', 100)->count(),
                    'total_reading_time' => MemberReadingHistory::where('member_id', $member->id)
                        ->sum('time_spent'),
                    'average_completion_rate' => $this->getAverageCompletionRate($member->id),
                ],
                'interaction_stats' => [
                    'total_ratings' => MemberStoryRating::where('member_id', $member->id)->count(),
                    'average_rating_given' => round(MemberStoryRating::where('member_id', $member->id)
                        ->avg('rating') ?? 0, 1),
                    'total_bookmarks' => MemberStoryInteraction::where('member_id', $member->id)
                        ->where('action', 'bookmark')->count(),
                    'total_likes' => MemberStoryInteraction::where('member_id', $member->id)
                        ->where('action', 'like')->count(),
                    'total_shares' => MemberStoryInteraction::where('member_id', $member->id)
                        ->where('action', 'share')->count(),
                ],
                'engagement_stats' => [
                    'days_active' => $this->getDaysActive($member->id),
                    'current_streak' => $this->getCurrentReadingStreak($member->id),
                    'longest_streak' => $this->getLongestReadingStreak($member->id),
                    'last_activity' => MemberReadingHistory::where('member_id', $member->id)
                        ->latest('last_read_at')->value('last_read_at'),
                ],
                'preferences' => [
                    'favorite_categories' => $this->getFavoriteCategories($member->id),
                    'reading_patterns' => $this->getReadingPatterns($member->id),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Get member stats error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics',
            ], 500);
        }
    }

    /**
     * Forgot password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:members,email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Here you would implement password reset logic
            // For now, just return success message
            return response()->json([
                'success' => true,
                'message' => 'Password reset instructions sent to your email',
            ]);
        } catch (\Exception $e) {
            Log::error('Forgot password error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process password reset request',
            ], 500);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:members,email',
                'token' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Here you would implement password reset verification and update
            // For now, just return success message
            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Reset password error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
            ], 500);
        }
    }

    /**
     * Get progress status from reading progress
     */
    private function getProgressStatus(int $progress): string
    {
        return match (true) {
            $progress === 0 => 'not_started',
            $progress > 0 && $progress < 10 => 'just_started',
            $progress >= 10 && $progress < 90 => 'in_progress',
            $progress >= 90 && $progress < 100 => 'almost_done',
            $progress >= 100 => 'completed',
            default => 'unknown',
        };
    }

    /**
     * Generate recommendations for a member
     *
     * @param Member $member
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    private function generateRecommendations(Member $member, int $limit): array
    {
        // Get member's favorite categories (based on high ratings)
        $favoriteCategories = MemberStoryRating::where('member_id', $member->id)
            ->where('rating', '>=', 4)
            ->join('stories', 'member_story_ratings.story_id', '=', 'stories.id')
            ->groupBy('stories.category_id')
            ->selectRaw('stories.category_id, COUNT(*) as count')
            ->orderByDesc('count')
            ->limit(3)
            ->pluck('category_id')
            ->toArray();

        // Get stories member hasn't read from favorite categories
        $readStoryIds = MemberReadingHistory::where('member_id', $member->id)
            ->pluck('story_id')
            ->toArray();

        $recommendations = Story::where('active', true)
            ->whereNotIn('id', $readStoryIds)
            ->when(!empty($favoriteCategories), function ($query) use ($favoriteCategories) {
                $query->whereIn('category_id', $favoriteCategories);
            })
            ->with(['category:id,name', 'ratingAggregate'])
            ->select(['id', 'title', 'excerpt', 'image', 'category_id', 'reading_time_minutes', 'created_at'])
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->toArray();

        return $recommendations;
    }

    /**
     * Get average completion rate for a member
     */
    private function getAverageCompletionRate(int $memberId): float
    {
        $totalStories = MemberReadingHistory::where('member_id', $memberId)->count();
        $completedStories = MemberReadingHistory::where('member_id', $memberId)
            ->where('reading_progress', '>=', 100)->count();

        return $totalStories > 0 ? round(($completedStories / $totalStories) * 100, 1) : 0;
    }

    /**
     * Get number of days member has been active
     */
    private function getDaysActive(int $memberId): int
    {
        return MemberReadingHistory::where('member_id', $memberId)
            ->selectRaw('DATE(last_read_at) as read_date')
            ->distinct()
            ->count();
    }

    /**
     * Get current reading streak
     */
    private function getCurrentReadingStreak(int $memberId): int
    {
        // Simplified streak calculation
        // In production, implement proper streak logic based on consecutive reading days
        $recentReadingDays = MemberReadingHistory::where('member_id', $memberId)
            ->where('last_read_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(last_read_at) as read_date')
            ->distinct()
            ->orderByDesc('read_date')
            ->pluck('read_date')
            ->toArray();

        $streak = 0;
        $currentDate = now()->format('Y-m-d');

        foreach ($recentReadingDays as $date) {
            if ($date === $currentDate || $date === now()->subDay()->format('Y-m-d')) {
                $streak++;
                $currentDate = $date;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get longest reading streak
     */
    private function getLongestReadingStreak(int $memberId): int
    {
        // Simplified streak calculation
        // In production, implement proper streak logic to find the longest consecutive streak
        $allReadingDays = MemberReadingHistory::where('member_id', $memberId)
            ->selectRaw('DATE(last_read_at) as read_date')
            ->distinct()
            ->orderBy('read_date')
            ->pluck('read_date')
            ->toArray();

        if (empty($allReadingDays)) {
            return 0;
        }

        $longestStreak = 1;
        $currentStreak = 1;

        for ($i = 1; $i < count($allReadingDays); $i++) {
            $currentDate = \Carbon\Carbon::parse($allReadingDays[$i]);
            $previousDate = \Carbon\Carbon::parse($allReadingDays[$i - 1]);

            if ($currentDate->diffInDays($previousDate) === 1) {
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } else {
                $currentStreak = 1;
            }
        }

        return $longestStreak;
    }

    /**
     * Get member's favorite categories
     *
     * @param int $memberId
     * @return array<int, array<string, mixed>>
     */
    private function getFavoriteCategories(int $memberId): array
    {
        return MemberStoryRating::where('member_id', $memberId)
            ->where('rating', '>=', 4)
            ->join('stories', 'member_story_ratings.story_id', '=', 'stories.id')
            ->join('categories', 'stories.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.id, categories.name, COUNT(*) as count')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->toArray();
    }

    /**
     * Get reading patterns for a member
     *
     * @param int $memberId
     * @return array<string, mixed>
     */
    private function getReadingPatterns(int $memberId): array
    {
        // Get average session duration
        $averageSessionDuration = MemberReadingHistory::where('member_id', $memberId)
            ->avg('time_spent') ?? 0;

        // Get preferred reading time (simplified - based on when most reading happens)
        $hourlyReadingActivity = MemberReadingHistory::where('member_id', $memberId)
            ->selectRaw('HOUR(last_read_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        $preferredTime = 'evening'; // default
        if ($hourlyReadingActivity) {
            $hour = $hourlyReadingActivity->hour;
            $preferredTime = match (true) {
                $hour >= 6 && $hour < 12 => 'morning',
                $hour >= 12 && $hour < 18 => 'afternoon',
                $hour >= 18 && $hour < 24 => 'evening',
                default => 'night',
            };
        }

        return [
            'preferred_reading_time' => $preferredTime,
            'average_session_duration' => round($averageSessionDuration / 60, 1), // Convert to minutes
            'completion_rate' => $this->getAverageCompletionRate($memberId),
        ];
    }
}