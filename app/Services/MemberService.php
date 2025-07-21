<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Member;
use App\Models\MemberReadingHistory;
use App\Models\MemberStoryInteraction;
use App\Models\MemberStoryRating;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Member Service
 * 
 * Handles business logic related to member operations and statistics.
 * Provides methods for account validation, reading analytics, and member data processing.
 * 
 * @author Development Team
 * @version 1.0.0
 */
class MemberService
{
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
        
        // Check if email already exists
        $existingMember = Member::where('email', $normalizedEmail)->first();
        
        if ($existingMember) {
            // Don't allow registration if account exists and is active
            return $existingMember->status !== 'active';
        }
        
        // Add any additional business rules here
        // e.g., check against blacklisted domains, temporary emails, etc.
        
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
               $member->email_verified_at !== null;
    }

    /**
     * Get comprehensive reading statistics for a member
     * 
     * @param int $memberId
     * @return array
     */
    public function getReadingStatistics(int $memberId): array
    {
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
            ],
            'engagement' => [
                'total_interactions' => $this->getTotalInteractions($memberId),
                'stories_rated' => $this->getTotalRatingsGiven($memberId),
                'average_rating_given' => $this->getAverageRatingGiven($memberId),
            ],
            'achievements' => $this->getReadingAchievements($memberId),
        ];
    }

    /**
     * Get comprehensive reading stats with caching
     * 
     * @param int $memberId
     * @return array
     */
    public function getComprehensiveReadingStats(int $memberId): array
    {
        return [
            'overview' => [
                'total_stories_started' => MemberReadingHistory::where('member_id', $memberId)
                    ->where('reading_progress', '>', 0)->count(),
                'completed_stories' => MemberReadingHistory::where('member_id', $memberId)
                    ->where('reading_progress', '>=', 100)->count(),
                'in_progress_stories' => MemberReadingHistory::where('member_id', $memberId)
                    ->whereBetween('reading_progress', [1, 99])->count(),
                'total_reading_time_minutes' => MemberReadingHistory::where('member_id', $memberId)
                    ->sum('time_spent') / 60,
            ],
            'reading_patterns' => [
                'average_completion_rate' => $this->getAverageCompletionRate($memberId),
                'favorite_reading_times' => $this->getFavoriteReadingTimes($memberId),
                'reading_streak_days' => $this->getReadingStreak($memberId),
            ],
            'engagement_metrics' => [
                'stories_bookmarked' => MemberStoryInteraction::where('member_id', $memberId)
                    ->where('action', 'bookmark')->count(),
                'stories_shared' => MemberStoryInteraction::where('member_id', $memberId)
                    ->where('action', 'share')->count(),
                'stories_liked' => MemberStoryInteraction::where('member_id', $memberId)
                    ->where('action', 'like')->count(),
                'total_ratings_given' => MemberStoryRating::where('member_id', $memberId)->count(),
            ],
        ];
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
            $progress >= 10 && $progress < 90 => 'in_progress',
            $progress >= 90 && $progress < 100 => 'almost_done',
            $progress >= 100 => 'completed',
            default => 'unknown',
        };
    }

    /**
     * Get bulk member interactions for multiple stories
     * 
     * @param int $memberId
     * @param array $storyIds
     * @return array
     */
    public function getBulkMemberInteractions(int $memberId, array $storyIds): array
    {
        // Get all ratings for the stories
        $ratings = MemberStoryRating::where('member_id', $memberId)
            ->whereIn('story_id', $storyIds)
            ->get()
            ->keyBy('story_id');

        // Get all interactions for the stories
        $interactions = MemberStoryInteraction::where('member_id', $memberId)
            ->whereIn('story_id', $storyIds)
            ->get()
            ->groupBy('story_id');

        // Get all reading progress for the stories
        $readingHistory = MemberReadingHistory::where('member_id', $memberId)
            ->whereIn('story_id', $storyIds)
            ->get()
            ->keyBy('story_id');

        // Build response array
        $result = [];
        foreach ($storyIds as $storyId) {
            $storyInteractions = $interactions[$storyId] ?? collect();
            $actionsList = $storyInteractions->pluck('action')->toArray();
            $rating = $ratings[$storyId] ?? null;
            $progress = $readingHistory[$storyId] ?? null;

            $result[$storyId] = [
                'has_rated' => $rating !== null,
                'rating' => $rating?->rating,
                'has_bookmarked' => in_array('bookmark', $actionsList),
                'has_liked' => in_array('like', $actionsList),
                'has_shared' => in_array('share', $actionsList),
                'has_viewed' => in_array('view', $actionsList),
                'reading_progress' => $progress?->reading_progress ?? 0,
                'is_completed' => ($progress?->reading_progress ?? 0) >= 100,
            ];
        }

        return $result;
    }

    // ===== PRIVATE HELPER METHODS =====

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
        return (int) MemberReadingHistory::where('member_id', $memberId)
            ->sum('time_spent') / 60;
    }

    /**
     * Get average session time
     */
    private function getAverageSessionTime(int $memberId): float
    {
        $totalSessions = MemberReadingHistory::where('member_id', $memberId)
            ->sum('reading_sessions');
            
        if ($totalSessions === 0) {
            return 0;
        }

        return round($this->getTotalReadingTime($memberId) / $totalSessions, 1);
    }

    /**
     * Get longest reading session time
     */
    private function getLongestSessionTime(int $memberId): int
    {
        // This would need additional tracking in the reading history table
        // For now, return estimated value
        return (int) MemberReadingHistory::where('member_id', $memberId)
            ->max('time_spent') / 60;
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
     * Get reading achievements for gamification
     */
    private function getReadingAchievements(int $memberId): array
    {
        $achievements = [];
        $completedCount = $this->getCompletedStoriesCount($memberId);
        $totalTime = $this->getTotalReadingTime($memberId);

        // Reading milestones
        if ($completedCount >= 1) {
            $achievements[] = ['type' => 'first_story', 'title' => 'First Story Complete'];
        }
        if ($completedCount >= 10) {
            $achievements[] = ['type' => 'story_explorer', 'title' => 'Story Explorer'];
        }
        if ($completedCount >= 50) {
            $achievements[] = ['type' => 'bookworm', 'title' => 'Bookworm'];
        }

        // Time-based achievements
        if ($totalTime >= 60) { // 1 hour
            $achievements[] = ['type' => 'dedicated_reader', 'title' => 'Dedicated Reader'];
        }
        if ($totalTime >= 600) { // 10 hours
            $achievements[] = ['type' => 'reading_marathon', 'title' => 'Reading Marathon'];
        }

        return $achievements;
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
     * Get favorite reading times (placeholder for future enhancement)
     */
    private function getFavoriteReadingTimes(int $memberId): array
    {
        // This would require tracking reading session times
        // For now, return empty array
        return [];
    }

    /**
     * Get reading streak in days
     */
    private function getReadingStreak(int $memberId): int
    {
        // Calculate consecutive days with reading activity
        $recentActivity = MemberReadingHistory::where('member_id', $memberId)
            ->where('last_read_at', '>=', now()->subDays(30))
            ->orderByDesc('last_read_at')
            ->pluck('last_read_at')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();

        if (empty($recentActivity)) {
            return 0;
        }

        // Calculate streak from today backwards
        $streak = 0;
        $currentDate = now()->format('Y-m-d');

        foreach ($recentActivity as $activityDate) {
            if ($activityDate === $currentDate) {
                $streak++;
                $currentDate = now()->subDays($streak)->format('Y-m-d');
            } else {
                break;
            }
        }

        return $streak;
    }
}