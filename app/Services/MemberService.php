<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Member;
use App\Models\MemberReadingHistory;
use App\Models\MemberStoryInteraction;
use App\Models\MemberStoryRating;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Member Service
 * 
 * Handles business logic related to member operations and statistics.
 * Provides methods for account validation, reading analytics, and member data processing.
 * 
 * @author Development Team
 * @version 1.1.0
 */
class MemberService
{
    /**
     * Cache TTL constants
     */
    private const CACHE_SHORT = 300; // 5 minutes
    private const CACHE_MEDIUM = 900; // 15 minutes
    private const CACHE_LONG = 3600; // 1 hour

    /**
     * Check if email can be used for registration
     * 
     * @param string $email
     * @return bool
     */
    public function canRegisterWithEmail(string $email): bool
    {
        // Normalize email for comparison
        $normalizedEmail = strtolower(trim($email));

        // Validate email format first
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Check if email already exists
        $existingMember = Member::where('email', $normalizedEmail)->first();

        if ($existingMember) {
            // Don't allow registration if account exists and is active
            return $existingMember->status !== 'active';
        }

        // Additional business rules
        if ($this->isBlacklistedEmail($normalizedEmail)) {
            return false;
        }

        if ($this->isTemporaryEmail($normalizedEmail)) {
            return false;
        }

        return true;
    }

    /**
     * Check if member account is active and can login
     * 
     * @param Member $member
     * @return bool
     */
    public function isAccountActive(Member $member): bool
    {
        return $member->status === 'active' &&
            $member->email_verified_at !== null &&
            !$this->isAccountSuspended($member) &&
            !$this->isAccountExpired($member);
    }

    /**
     * Get comprehensive reading statistics for a member with caching
     * 
     * @param int $memberId
     * @return array
     */
    public function getReadingStatistics(int $memberId): array
    {
        $cacheKey = "member_reading_stats_{$memberId}";

        return Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($memberId) {
            return [
                'stories_read' => [
                    'completed' => $this->getCompletedStoriesCount($memberId),
                    'in_progress' => $this->getInProgressStoriesCount($memberId),
                    'total_started' => $this->getTotalStartedStoriesCount($memberId),
                ],
                'reading_time' => [
                    'total_minutes' => $this->getTotalReadingTime($memberId),
                    'average_session' => $this->getAverageSessionTime($memberId),
                    'longest_session' => $this->getLongestSessionTime($memberId),
                    'estimated_hours' => round($this->getTotalReadingTime($memberId) / 60, 1),
                ],
                'engagement' => [
                    'total_interactions' => $this->getTotalInteractions($memberId),
                    'stories_rated' => $this->getTotalRatingsGiven($memberId),
                    'average_rating_given' => $this->getAverageRatingGiven($memberId),
                    'stories_bookmarked' => $this->getBookmarkedStoriesCount($memberId),
                    'stories_shared' => $this->getSharedStoriesCount($memberId),
                ],
                'achievements' => $this->getReadingBadges($memberId),
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get comprehensive reading stats with enhanced caching and error handling
     * 
     * @param int $memberId
     * @return array
     */
    public function getComprehensiveReadingStats(int $memberId): array
    {
        $cacheKey = "member_comprehensive_stats_{$memberId}";

        return Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($memberId) {
            try {
                $totalStarted = MemberReadingHistory::where('member_id', $memberId)
                    ->where('reading_progress', '>', 0)->count();

                $completed = MemberReadingHistory::where('member_id', $memberId)
                    ->where('reading_progress', '>=', 100)->count();

                $inProgress = MemberReadingHistory::where('member_id', $memberId)
                    ->whereBetween('reading_progress', [1, 99])->count();

                $totalTimeSeconds = MemberReadingHistory::where('member_id', $memberId)
                    ->sum('time_spent') ?? 0;

                return [
                    'overview' => [
                        'total_stories_started' => $totalStarted,
                        'completed_stories' => $completed,
                        'in_progress_stories' => $inProgress,
                        'not_started_count' => max(0, $totalStarted - $completed - $inProgress),
                        'total_reading_time_minutes' => round($totalTimeSeconds / 60, 1),
                        'total_reading_time_hours' => round($totalTimeSeconds / 3600, 2),
                        'completion_percentage' => $totalStarted > 0 ? round(($completed / $totalStarted) * 100, 1) : 0,
                    ],
                    'reading_patterns' => [
                        'average_completion_rate' => $this->getAverageCompletionRate($memberId),
                        'favorite_reading_times' => $this->getFavoriteReadingTimes($memberId),
                        'reading_streak_days' => $this->getReadingStreak($memberId),
                        'most_active_day' => $this->getMostActiveReadingDay($memberId),
                        'reading_consistency' => $this->getReadingConsistency($memberId),
                    ],
                    'engagement_metrics' => [
                        'stories_bookmarked' => MemberStoryInteraction::where('member_id', $memberId)
                            ->where('action', 'bookmark')->count(),
                        'stories_shared' => MemberStoryInteraction::where('member_id', $memberId)
                            ->where('action', 'share')->count(),
                        'stories_liked' => MemberStoryInteraction::where('member_id', $memberId)
                            ->where('action', 'like')->count(),
                        'total_ratings_given' => MemberStoryRating::where('member_id', $memberId)->count(),
                        'average_rating_given' => $this->getAverageRatingGiven($memberId),
                        'engagement_score' => $this->calculateEngagementScore($memberId),
                    ],
                    'preferences' => [
                        'preferred_story_length' => $this->getPreferredStoryLength($memberId),
                        'favorite_genres' => $this->getFavoriteGenres($memberId),
                        'reading_speed_wpm' => $this->estimateReadingSpeed($memberId),
                    ],
                ];
            } catch (\Exception $e) {
                Log::error('Error getting comprehensive reading stats', [
                    'member_id' => $memberId,
                    'error' => $e->getMessage(),
                ]);

                return $this->getEmptyStatsStructure();
            }
        });
    }

    /**
     * Get progress status from reading progress percentage
     * 
     * @param float $progress
     * @return string
     */
    public function getProgressStatus(float $progress): string
    {
        return match (true) {
            $progress === 0.0 => 'not_started',
            $progress > 0 && $progress < 10 => 'just_started',
            $progress >= 10 && $progress < 25 => 'getting_started',
            $progress >= 25 && $progress < 50 => 'making_progress',
            $progress >= 50 && $progress < 75 => 'halfway_through',
            $progress >= 75 && $progress < 90 => 'almost_there',
            $progress >= 90 && $progress < 100 => 'almost_done',
            $progress >= 100 => 'completed',
            default => 'unknown',
        };
    }

    /**
     * FIXED: Get bulk member interactions for multiple stories with optimized queries
     * 
     * @param int $memberId
     * @param array $storyIds
     * @return array
     */
    public function getBulkMemberInteractions(int $memberId, array $storyIds): array
    {
        if (empty($storyIds)) {
            return [];
        }

        // Validate and sanitize story IDs
        $storyIds = array_filter(array_map('intval', $storyIds), fn($id) => $id > 0);

        if (empty($storyIds)) {
            return [];
        }

        $cacheKey = "bulk_interactions_{$memberId}_" . md5(implode(',', $storyIds));

        return Cache::remember($cacheKey, self::CACHE_SHORT, function () use ($memberId, $storyIds) {
            try {
                // Get all ratings for the stories in a single query
                $ratings = MemberStoryRating::where('member_id', $memberId)
                    ->whereIn('story_id', $storyIds)
                    ->select(['story_id', 'rating', 'comment', 'created_at'])
                    ->get()
                    ->keyBy('story_id');

                // Get all interactions for the stories in a single query
                $interactions = MemberStoryInteraction::where('member_id', $memberId)
                    ->whereIn('story_id', $storyIds)
                    ->select(['story_id', 'action', 'created_at'])
                    ->get()
                    ->groupBy('story_id');

                // Get all reading progress for the stories in a single query
                $readingHistory = MemberReadingHistory::where('member_id', $memberId)
                    ->whereIn('story_id', $storyIds)
                    ->select(['story_id', 'reading_progress', 'time_spent', 'last_read_at'])
                    ->get()
                    ->keyBy('story_id');

                // Build comprehensive response array
                $result = [];
                foreach ($storyIds as $storyId) {
                    $storyInteractions = $interactions->get($storyId, collect());
                    $actionsList = $storyInteractions->pluck('action')->toArray();
                    $rating = $ratings->get($storyId);
                    $progress = $readingHistory->get($storyId);

                    $result[$storyId] = [
                        // Rating information
                        'rating' => [
                            'has_rated' => $rating !== null,
                            'rating' => $rating?->rating,
                            'comment' => $rating?->comment,
                            'rated_at' => $rating?->created_at?->toISOString(),
                        ],

                        // Interaction flags
                        'interactions' => [
                            'has_bookmarked' => in_array('bookmark', $actionsList),
                            'has_liked' => in_array('like', $actionsList),
                            'has_shared' => in_array('share', $actionsList),
                            'has_viewed' => in_array('view', $actionsList),
                            'interaction_count' => count($actionsList),
                            'last_interaction' => $storyInteractions->max('created_at')?->toISOString(),
                        ],

                        // Reading progress
                        'reading_progress' => [
                            'progress_percentage' => $progress?->reading_progress ?? 0,
                            'time_spent_seconds' => $progress?->time_spent ?? 0,
                            'time_spent_minutes' => round(($progress?->time_spent ?? 0) / 60, 1),
                            'last_read_at' => $progress?->last_read_at?->toISOString(),
                            'is_completed' => ($progress?->reading_progress ?? 0) >= 100,
                            'status' => $this->getProgressStatus($progress?->reading_progress ?? 0),
                        ],

                        // Summary flags for quick access
                        'summary' => [
                            'has_any_interaction' => !empty($actionsList),
                            'has_started_reading' => ($progress?->reading_progress ?? 0) > 0,
                            'completion_status' => $this->getProgressStatus($progress?->reading_progress ?? 0),
                            'engagement_level' => $this->calculateStoryEngagementLevel($actionsList, $rating, $progress),
                        ],
                    ];
                }

                return $result;
            } catch (\Exception $e) {
                Log::error('Error getting bulk member interactions', [
                    'member_id' => $memberId,
                    'story_ids' => $storyIds,
                    'error' => $e->getMessage(),
                ]);

                // Return empty structure for all story IDs on error
                return array_fill_keys($storyIds, $this->getEmptyInteractionStructure());
            }
        });
    }

    /**
     * Clear member-related caches
     * 
     * @param int $memberId
     * @return void
     */
    public function clearMemberCaches(int $memberId): void
    {
        $patterns = [
            "member_reading_stats_{$memberId}",
            "member_comprehensive_stats_{$memberId}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }

        // Clear bulk interaction caches (this is more complex due to the hash)
        // In a production environment, you might want to use cache tags
        Cache::flush(); // This is aggressive but ensures consistency
    }

    // ===== PRIVATE HELPER METHODS =====

    /**
     * Check if email is blacklisted
     */
    private function isBlacklistedEmail(string $email): bool
    {
        $blacklistedDomains = [
            '10minutemail.com',
            'tempmail.org',
            'guerrillamail.com',
            'mailinator.com',
            // Add more as needed
        ];

        $domain = substr(strrchr($email, '@'), 1);
        return in_array($domain, $blacklistedDomains);
    }

    /**
     * Check if email is from a temporary email service
     */
    private function isTemporaryEmail(string $email): bool
    {
        // This could be expanded with a more comprehensive list
        // or integration with a temporary email detection service
        $tempPatterns = [
            '/temp/',
            '/disposable/',
            '/throwaway/',
            '/fake/',
        ];

        foreach ($tempPatterns as $pattern) {
            if (preg_match($pattern, $email)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if account is suspended
     */
    private function isAccountSuspended(Member $member): bool
    {
        return $member->status === 'suspended' ||
            $member->status === 'banned';
    }

    /**
     * Check if account is expired
     */
    private function isAccountExpired(Member $member): bool
    {
        // Implement account expiration logic if needed
        return false;
    }

    /**
     * Get count of completed stories
     */
    private function getCompletedStoriesCount(int $memberId): int
    {
        return MemberReadingHistory::where('member_id', $memberId)
            ->where('reading_progress', '>=', 100)
            ->count();
    }

    /**
     * Get count of stories in progress
     */
    private function getInProgressStoriesCount(int $memberId): int
    {
        return MemberReadingHistory::where('member_id', $memberId)
            ->whereBetween('reading_progress', [1, 99])
            ->count();
    }

    /**
     * Get total count of started stories
     */
    private function getTotalStartedStoriesCount(int $memberId): int
    {
        return MemberReadingHistory::where('member_id', $memberId)
            ->where('reading_progress', '>', 0)
            ->count();
    }

    /**
     * Get total reading time in minutes
     */
    private function getTotalReadingTime(int $memberId): int
    {
        return (int) round(
            (MemberReadingHistory::where('member_id', $memberId)->sum('time_spent') ?? 0) / 60
        );
    }

    /**
     * Get average session time
     */
    private function getAverageSessionTime(int $memberId): float
    {
        $readingHistory = MemberReadingHistory::where('member_id', $memberId)
            ->where('time_spent', '>', 0)
            ->get();

        if ($readingHistory->isEmpty()) {
            return 0.0;
        }

        $totalTime = $readingHistory->sum('time_spent') / 60; // Convert to minutes
        $sessionCount = $readingHistory->count();

        return round($totalTime / $sessionCount, 1);
    }

    /**
     * Get longest reading session time
     */
    private function getLongestSessionTime(int $memberId): int
    {
        return (int) round(
            (MemberReadingHistory::where('member_id', $memberId)->max('time_spent') ?? 0) / 60
        );
    }

    /**
     * Get total interactions count
     */
    private function getTotalInteractions(int $memberId): int
    {
        return MemberStoryInteraction::where('member_id', $memberId)->count();
    }

    /**
     * Get total ratings given
     */
    private function getTotalRatingsGiven(int $memberId): int
    {
        return MemberStoryRating::where('member_id', $memberId)->count();
    }

    /**
     * Get average rating given by member
     */
    private function getAverageRatingGiven(int $memberId): float
    {
        return round(
            MemberStoryRating::where('member_id', $memberId)->avg('rating') ?? 0,
            1
        );
    }

    /**
     * Get bookmarked stories count
     */
    private function getBookmarkedStoriesCount(int $memberId): int
    {
        return MemberStoryInteraction::where('member_id', $memberId)
            ->where('action', 'bookmark')
            ->count();
    }

    /**
     * Get shared stories count
     */
    private function getSharedStoriesCount(int $memberId): int
    {
        return MemberStoryInteraction::where('member_id', $memberId)
            ->where('action', 'share')
            ->count();
    }

    /**
     * Get reading badges for gamification (renamed from getReadingAchievements)
     * Used internally for achievements display
     * 
     * @param int $memberId
     * @return array
     */
    private function getReadingBadges(int $memberId): array
    {
        $achievements = [];
        $completedCount = $this->getCompletedStoriesCount($memberId);
        $totalTime = $this->getTotalReadingTime($memberId);
        $ratingsGiven = $this->getTotalRatingsGiven($memberId);
        $streak = $this->getReadingStreak($memberId);

        // Reading milestones
        if ($completedCount >= 1) {
            $achievements[] = [
                'type' => 'first_story',
                'title' => 'First Story Complete',
                'description' => 'Completed your first story',
                'unlocked_at' => 'recently',
            ];
        }
        if ($completedCount >= 5) {
            $achievements[] = [
                'type' => 'story_enthusiast',
                'title' => 'Story Enthusiast',
                'description' => 'Completed 5 stories',
            ];
        }
        if ($completedCount >= 10) {
            $achievements[] = [
                'type' => 'story_explorer',
                'title' => 'Story Explorer',
                'description' => 'Completed 10 stories',
            ];
        }
        if ($completedCount >= 50) {
            $achievements[] = [
                'type' => 'bookworm',
                'title' => 'Bookworm',
                'description' => 'Completed 50 stories',
            ];
        }
        if ($completedCount >= 100) {
            $achievements[] = [
                'type' => 'story_master',
                'title' => 'Story Master',
                'description' => 'Completed 100 stories',
            ];
        }

        // Time-based achievements
        if ($totalTime >= 60) { // 1 hour
            $achievements[] = [
                'type' => 'dedicated_reader',
                'title' => 'Dedicated Reader',
                'description' => 'Spent 1 hour reading',
            ];
        }
        if ($totalTime >= 600) { // 10 hours
            $achievements[] = [
                'type' => 'reading_marathon',
                'title' => 'Reading Marathon',
                'description' => 'Spent 10 hours reading',
            ];
        }

        // Engagement achievements
        if ($ratingsGiven >= 10) {
            $achievements[] = [
                'type' => 'critic',
                'title' => 'Story Critic',
                'description' => 'Rated 10 stories',
            ];
        }

        // Streak achievements
        if ($streak >= 7) {
            $achievements[] = [
                'type' => 'week_streak',
                'title' => 'Week Warrior',
                'description' => '7-day reading streak',
            ];
        }
        if ($streak >= 30) {
            $achievements[] = [
                'type' => 'month_streak',
                'title' => 'Monthly Master',
                'description' => '30-day reading streak',
            ];
        }

        return $achievements;
    }

    /**
     * Get reading achievements for profile display (streak & words read)
     * PUBLIC method for API endpoint
     * 
     * @param int $memberId
     * @return array
     */
    public function getReadingAchievements(int $memberId): array
    {
        try {
            // 1. Get reading streak (uses existing method)
            $streak = $this->getReadingStreak($memberId);

            // 2. Get all completed stories (100% progress)
            $completedStories = MemberReadingHistory::where('member_id', $memberId)
                ->where('reading_progress', '>=', 100)
                ->with('story:id,reading_time_minutes')
                ->get();

            // 3. Calculate total words read
            // Formula: reading_time_minutes * 200 WPM (your existing calculation)
            $totalWords = 0;
            foreach ($completedStories as $history) {
                if ($history->story && $history->story->reading_time_minutes > 0) {
                    $totalWords += ($history->story->reading_time_minutes * 200);
                }
            }

            return [
                'reading_streak_days' => $streak,
                'total_words_read' => $totalWords,
                'completed_stories_count' => $completedStories->count(),
                'last_updated' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating reading achievements', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            // Return empty state on error
            return [
                'reading_streak_days' => 0,
                'total_words_read' => 0,
                'completed_stories_count' => 0,
                'last_updated' => now()->toISOString(),
            ];
        }
    }

    /**
     * Get average completion rate
     */
    private function getAverageCompletionRate(int $memberId): float
    {
        $avgProgress = MemberReadingHistory::where('member_id', $memberId)
            ->avg('reading_progress');

        return round($avgProgress ?? 0, 1);
    }

    /**
     * Enhanced: Get favorite reading times with actual data analysis
     */
    private function getFavoriteReadingTimes(int $memberId): array
    {
        $readingSessions = MemberReadingHistory::where('member_id', $memberId)
            ->whereNotNull('last_read_at')
            ->select(['last_read_at'])
            ->get();

        if ($readingSessions->isEmpty()) {
            return [];
        }

        $hourCounts = [];
        foreach ($readingSessions as $session) {
            $hour = $session->last_read_at->format('H');
            $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
        }

        arsort($hourCounts);
        $topHours = array_slice($hourCounts, 0, 3, true);

        $timeLabels = [
            '06' => 'Early Morning',
            '07' => 'Early Morning',
            '08' => 'Morning',
            '09' => 'Morning',
            '10' => 'Morning',
            '11' => 'Late Morning',
            '12' => 'Noon',
            '13' => 'Afternoon',
            '14' => 'Afternoon',
            '15' => 'Afternoon',
            '16' => 'Late Afternoon',
            '17' => 'Late Afternoon',
            '18' => 'Evening',
            '19' => 'Evening',
            '20' => 'Evening',
            '21' => 'Night',
            '22' => 'Night',
            '23' => 'Late Night',
            '00' => 'Midnight',
            '01' => 'Late Night',
            '02' => 'Late Night',
        ];

        return array_map(function ($hour) use ($timeLabels) {
            return [
                'hour' => $hour,
                'label' => $timeLabels[$hour] ?? 'Unknown',
                'time_range' => sprintf('%02d:00-%02d:59', $hour, $hour),
            ];
        }, array_keys($topHours));
    }

    /**
     * Get reading streak in days with improved calculation
     */
    private function getReadingStreak(int $memberId): int
    {
        $recentActivity = MemberReadingHistory::where('member_id', $memberId)
            ->where('last_read_at', '>=', now()->subDays(365)) // Look back 1 year max
            ->orderByDesc('last_read_at')
            ->pluck('last_read_at')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();

        if (empty($recentActivity)) {
            return 0;
        }

        // Calculate consecutive days from most recent
        $streak = 0;
        $expectedDate = now()->format('Y-m-d');

        foreach ($recentActivity as $activityDate) {
            if ($activityDate === $expectedDate) {
                $streak++;
                $expectedDate = now()->subDays($streak)->format('Y-m-d');
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get most active reading day
     */
    private function getMostActiveReadingDay(int $memberId): string
    {
        $dayCounts = MemberReadingHistory::where('member_id', $memberId)
            ->whereNotNull('last_read_at')
            ->selectRaw('DAYNAME(last_read_at) as day_name, COUNT(*) as count')
            ->groupBy('day_name')
            ->orderByDesc('count')
            ->first();

        return $dayCounts?->day_name ?? 'No data';
    }

    /**
     * Calculate reading consistency score
     */
    private function getReadingConsistency(int $memberId): float
    {
        $readingDays = MemberReadingHistory::where('member_id', $memberId)
            ->where('last_read_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(last_read_at) as reading_date')
            ->groupBy('reading_date')
            ->count();

        return round(($readingDays / 30) * 100, 1);
    }

    /**
     * Calculate engagement score
     */
    private function calculateEngagementScore(int $memberId): float
    {
        $interactions = $this->getTotalInteractions($memberId);
        $ratings = $this->getTotalRatingsGiven($memberId);
        $completed = $this->getCompletedStoriesCount($memberId);

        // Weighted scoring system
        $score = ($interactions * 0.3) + ($ratings * 0.5) + ($completed * 0.2);

        return round(min($score, 100), 1); // Cap at 100
    }

    /**
     * Get preferred story length
     */
    private function getPreferredStoryLength(int $memberId): string
    {
        // This would require story length data in the database
        // For now, return a placeholder
        return 'Medium (10-20 minutes)';
    }

    /**
     * Get favorite genres
     */
    private function getFavoriteGenres(int $memberId): array
    {
        // This would require category/genre data analysis
        // For now, return empty array
        return [];
    }

    /**
     * Estimate reading speed in words per minute
     */
    private function estimateReadingSpeed(int $memberId): int
    {
        // This would require word count data and reading time correlation
        // Average reading speed is 200-250 WPM
        return 225; // Default estimate
    }

    /**
     * Calculate story-specific engagement level
     */
    private function calculateStoryEngagementLevel(array $actions, $rating, $progress): string
    {
        $score = 0;

        if (in_array('view', $actions)) $score += 1;
        if (in_array('like', $actions)) $score += 2;
        if (in_array('bookmark', $actions)) $score += 3;
        if (in_array('share', $actions)) $score += 4;
        if ($rating) $score += 3;
        if (($progress?->reading_progress ?? 0) >= 100) $score += 5;

        return match (true) {
            $score === 0 => 'none',
            $score <= 2 => 'low',
            $score <= 6 => 'medium',
            $score <= 10 => 'high',
            default => 'very_high',
        };
    }

    /**
     * Get empty stats structure for error cases
     */
    private function getEmptyStatsStructure(): array
    {
        return [
            'overview' => [
                'total_stories_started' => 0,
                'completed_stories' => 0,
                'in_progress_stories' => 0,
                'total_reading_time_minutes' => 0,
            ],
            'reading_patterns' => [
                'average_completion_rate' => 0,
                'favorite_reading_times' => [],
                'reading_streak_days' => 0,
            ],
            'engagement_metrics' => [
                'stories_bookmarked' => 0,
                'stories_shared' => 0,
                'stories_liked' => 0,
                'total_ratings_given' => 0,
            ],
        ];
    }

    /**
     * Get empty interaction structure for error cases
     */
    private function getEmptyInteractionStructure(): array
    {
        return [
            'rating' => [
                'has_rated' => false,
                'rating' => null,
                'comment' => null,
                'rated_at' => null,
            ],
            'interactions' => [
                'has_bookmarked' => false,
                'has_liked' => false,
                'has_shared' => false,
                'has_viewed' => false,
                'interaction_count' => 0,
                'last_interaction' => null,
            ],
            'reading_progress' => [
                'progress_percentage' => 0,
                'time_spent_seconds' => 0,
                'time_spent_minutes' => 0,
                'last_read_at' => null,
                'is_completed' => false,
                'status' => 'not_started',
            ],
            'summary' => [
                'has_any_interaction' => false,
                'has_started_reading' => false,
                'completion_status' => 'not_started',
                'engagement_level' => 'none',
            ],
        ];
    }
}
