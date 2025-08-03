<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Member;
use App\Models\MemberReadingHistory;
use App\Models\MemberStoryInteraction;
use App\Models\MemberStoryRating;
use App\Models\Story;
use App\Models\StoryPublishingHistory;
use App\Models\StoryRatingAggregate;
use App\Models\StoryView;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoryAnalyticsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing analytics data
        $this->clearAnalyticsData();

        // Ensure we have basic data
        $this->ensureBasicData();

        // Generate analytics data for the last 90 days
        $this->generateStoryViews();
        $this->generateStoryInteractions();
        $this->generateStoryRatings();
        $this->generateReadingHistory();
        $this->generatePublishingHistory();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('✅ Story Analytics data seeded successfully!');
        $this->printSummary();
    }

    private function clearAnalyticsData(): void
    {
        $this->command->info('🧹 Clearing existing analytics data...');

        StoryView::truncate();
        MemberStoryInteraction::truncate();
        MemberStoryRating::truncate();
        StoryRatingAggregate::truncate();
        MemberReadingHistory::truncate();
        StoryPublishingHistory::truncate();
    }

    private function ensureBasicData(): void
    {
        $this->command->info('📊 Ensuring basic data exists...');

        // Ensure we have an admin user
        if (! User::exists()) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@dailystories.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }

        // Ensure we have categories
        if (! Category::exists()) {
            $categories = ['Adventure', 'Romance', 'Mystery', 'Science Fiction', 'Fantasy', 'Drama', 'Comedy', 'Thriller'];
            foreach ($categories as $category) {
                Category::create(['name' => $category]);
            }
        }

        // Ensure we have members
        if (Member::count() < 50) {
            $this->generateMembers();
        }

        // Ensure we have stories
        if (Story::count() < 20) {
            $this->generateStories();
        }
    }

    private function generateMembers(): void
    {
        $this->command->info('👥 Generating members...');

        $memberData = [];
        for ($i = 0; $i < 100; $i++) {
            $createdAt = fake()->dateTimeBetween('-6 months', 'now');
            $memberData[] = [
                'name' => fake()->name(),
                'email' => fake()->unique()->email(),
                'email_verified_at' => fake()->boolean(80) ? fake()->dateTimeBetween($createdAt, 'now') : null,
                'password' => bcrypt('password'),
                'status' => fake()->randomElement(['active', 'active', 'active', 'inactive']), // 75% active
                'last_login_at' => fake()->dateTimeBetween($createdAt, 'now'),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        Member::insert($memberData);
    }

    private function generateStories(): void
    {
        $this->command->info('📚 Generating stories...');

        $categories = Category::all();
        $titles = [
            'The Lost Kingdom', 'Midnight Adventures', 'Love in Paris', 'The Secret Garden',
            'Digital Dreams', 'The Time Traveler', 'Ocean Mysteries', 'Mountain Climber',
            'City Lights', 'Desert Wanderer', 'The Last Hero', 'Starlight Journey',
            'Forgotten Memories', 'Dancing in the Rain', 'The Magic Portal', 'Silent Whispers',
            'Beyond the Horizon', 'The Golden Crown', 'Mystic Forest', 'Eternal Flame',
            'The Crystal Cave', 'Shadow Hunter', 'Moonlit Path', 'The Phoenix Rising',
            'Hidden Treasures', 'The Enchanted Lake', 'Storm Chaser', 'The Ancient Scroll',
        ];

        foreach ($titles as $title) {
            $createdAt = fake()->dateTimeBetween('-3 months', '-1 week');

            Story::create([
                'title' => $title,
                'content' => fake()->paragraphs(fake()->numberBetween(15, 40), true),
                'excerpt' => fake()->sentence(20),
                'category_id' => $categories->random()->id,
                'image' => fake()->imageUrl(640, 480, 'abstract'),
                'views' => 0, // Will be calculated from StoryView
                'reading_time_minutes' => fake()->numberBetween(3, 15),
                'active' => fake()->boolean(85), // 85% active
                'active_from' => $createdAt,
                'active_until' => fake()->boolean(20) ? fake()->dateTimeBetween('now', '+6 months') : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function generateStoryViews(): void
    {
        $this->command->info('👀 Generating story views...');

        $stories = Story::all();
        $members = Member::all();
        $devices = $this->generateDeviceIds(200);

        $viewsData = [];
        $viewsCount = [];

        // Generate views for last 90 days
        for ($day = 89; $day >= 0; $day--) {
            $date = now()->subDays($day);

            // More views on weekends, trending up over time
            $baseViews = $day < 30 ? rand(150, 300) : ($day < 60 ? rand(100, 200) : rand(50, 150));
            $weekendBoost = $date->isWeekend() ? 1.5 : 1;
            $dailyViews = (int) ($baseViews * $weekendBoost);

            for ($i = 0; $i < $dailyViews; $i++) {
                $story = $stories->random();
                $member = fake()->boolean(60) ? $members->random() : null; // 60% member views
                $device = $devices[array_rand($devices)];

                $viewTime = $date->copy()->addHours(rand(6, 23))->addMinutes(rand(0, 59));

                $viewsData[] = [
                    'story_id' => $story->id,
                    'member_id' => $member?->id,
                    'device_id' => $device['id'],
                    'session_id' => 'sess_'.fake()->uuid(),
                    'user_agent' => $device['user_agent'],
                    'ip_address' => fake()->ipv4(),
                    'referrer' => fake()->randomElement([
                        null, 'https://google.com', 'https://facebook.com',
                        'https://twitter.com', 'https://reddit.com',
                    ]),
                    'viewed_at' => $viewTime,
                    'created_at' => $viewTime,
                    'updated_at' => $viewTime,
                ];

                // Track story view counts
                $viewsCount[$story->id] = ($viewsCount[$story->id] ?? 0) + 1;
            }
        }

        // Insert in batches
        collect($viewsData)->chunk(1000)->each(function ($chunk) {
            StoryView::insert($chunk->toArray());
        });

        // Update story view counts
        foreach ($viewsCount as $storyId => $count) {
            Story::where('id', $storyId)->update(['views' => $count]);
        }
    }

    private function generateStoryInteractions(): void
    {
        $this->command->info('💫 Generating story interactions...');

        $members = Member::all();
        $stories = Story::all();
        $actions = ['like', 'bookmark', 'share', 'dislike', 'report'];
        $actionWeights = [50, 25, 15, 8, 2]; // Like is most common

        $interactionsData = [];

        foreach ($members as $member) {
            // Each member interacts with 3-15 stories
            $storiesToInteract = $stories->random(rand(3, 15));

            foreach ($storiesToInteract as $story) {
                // Some members have multiple interactions per story
                $numInteractions = fake()->boolean(30) ? 2 : 1;

                for ($i = 0; $i < $numInteractions; $i++) {
                    $action = fake()->randomElement($actions);
                    $createdAt = fake()->dateTimeBetween('-30 days', 'now');

                    $interactionsData[] = [
                        'member_id' => $member->id,
                        'story_id' => $story->id,
                        'action' => $action,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
            }
        }

        // Remove duplicates (same member, story, action)
        $uniqueInteractions = collect($interactionsData)
            ->unique(function ($item) {
                return $item['member_id'].'-'.$item['story_id'].'-'.$item['action'];
            })
            ->values();

        // Insert in batches
        $uniqueInteractions->chunk(1000)->each(function ($chunk) {
            MemberStoryInteraction::insert($chunk->toArray());
        });
    }

    private function generateStoryRatings(): void
    {
        $this->command->info('⭐ Generating story ratings...');

        $members = Member::all();
        $stories = Story::all();

        $ratingsData = [];
        $aggregateData = [];

        foreach ($stories as $story) {
            // 30-80% of stories get ratings
            if (! fake()->boolean(60)) {
                continue;
            }

            $numRatings = fake()->numberBetween(5, 50);
            $storyRatings = [];

            // Select random members to rate this story
            $ratingMembers = $members->random(min($numRatings, $members->count()));

            foreach ($ratingMembers as $member) {
                // Rating distribution: more 4-5 stars, fewer 1-2 stars
                $rating = fake()->randomElement([5, 5, 5, 4, 4, 4, 4, 3, 3, 2, 1]);
                $createdAt = fake()->dateTimeBetween('-60 days', 'now');

                $ratingsData[] = [
                    'member_id' => $member->id,
                    'story_id' => $story->id,
                    'rating' => $rating,
                    'comment' => fake()->boolean(40) ? fake()->sentence(10) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                $storyRatings[] = $rating;
            }

            // Calculate aggregate data
            if (! empty($storyRatings)) {
                $totalRatings = count($storyRatings);
                $sumRatings = array_sum($storyRatings);
                $avgRating = $sumRatings / $totalRatings;

                // Rating distribution
                $distribution = [];
                for ($i = 1; $i <= 5; $i++) {
                    $distribution[$i] = count(array_filter($storyRatings, fn ($r) => $r === $i));
                }

                $aggregateData[] = [
                    'story_id' => $story->id,
                    'total_ratings' => $totalRatings,
                    'sum_ratings' => $sumRatings,
                    'average_rating' => round($avgRating, 2),
                    'rating_distribution' => json_encode($distribution),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Insert ratings
        collect($ratingsData)->chunk(1000)->each(function ($chunk) {
            MemberStoryRating::insert($chunk->toArray());
        });

        // Insert aggregates
        StoryRatingAggregate::insert($aggregateData);
    }

    private function generateReadingHistory(): void
    {
        $this->command->info('📖 Generating reading history...');

        $members = Member::all();
        $stories = Story::all();

        $historyData = [];

        foreach ($members as $member) {
            // Each member has read 2-10 stories
            $readStories = $stories->random(rand(2, 10));

            foreach ($readStories as $story) {
                $progress = fake()->randomElement([
                    100, 100, 100, 95, 90, 85, 75, 60, 45, 30, 15, 5,
                ]); // More completed reads

                $timeSpent = (int) (($story->reading_time_minutes * 60) * ($progress / 100) * fake()->randomFloat(1, 0.8, 1.5));
                $lastReadAt = fake()->dateTimeBetween('-30 days', 'now');

                $historyData[] = [
                    'member_id' => $member->id,
                    'story_id' => $story->id,
                    'reading_progress' => $progress,
                    'time_spent' => $timeSpent,
                    'last_read_at' => $lastReadAt,
                    'created_at' => $lastReadAt,
                    'updated_at' => $lastReadAt,
                ];
            }
        }

        // Remove duplicates
        $uniqueHistory = collect($historyData)
            ->unique(function ($item) {
                return $item['member_id'].'-'.$item['story_id'];
            })
            ->values();

        MemberReadingHistory::insert($uniqueHistory->toArray());
    }

    private function generatePublishingHistory(): void
    {
        $this->command->info('📝 Generating publishing history...');

        $stories = Story::all();
        $adminUser = User::first();
        $actions = ['published', 'updated', 'unpublished', 'scheduled', 'republished'];

        $historyData = [];

        foreach ($stories as $story) {
            // Each story has 1-5 publishing actions
            $numActions = rand(1, 5);

            for ($i = 0; $i < $numActions; $i++) {
                $action = $i === 0 ? 'published' : fake()->randomElement($actions);
                $createdAt = $i === 0
                    ? $story->created_at
                    : fake()->dateTimeBetween($story->created_at, 'now');

                $historyData[] = [
                    'story_id' => $story->id,
                    'user_id' => $adminUser->id,
                    'action' => $action,
                    'previous_active_status' => $i > 0 ? fake()->boolean() : null,
                    'new_active_status' => $action !== 'unpublished',
                    'previous_active_from' => $i > 0 ? fake()->dateTimeBetween('-1 month', 'now') : null,
                    'previous_active_until' => $i > 0 ? fake()->dateTimeBetween('now', '+1 month') : null,
                    'new_active_from' => $createdAt,
                    'new_active_until' => fake()->boolean(30) ? fake()->dateTimeBetween('now', '+6 months') : null,
                    'notes' => fake()->boolean(40) ? fake()->sentence(8) : null,
                    'changed_fields' => json_encode(['active', 'active_from']),
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        StoryPublishingHistory::insert($historyData);
    }

    private function generateDeviceIds(int $count): array
    {
        $devices = [];
        $userAgents = [
            // Mobile
            'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Android 11; Mobile; rv:94.0) Gecko/94.0 Firefox/94.0',
            'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.85 Mobile Safari/537.36',
            // Desktop
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.85 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.85 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:94.0) Gecko/20100101 Firefox/94.0',
        ];

        for ($i = 0; $i < $count; $i++) {
            $devices[] = [
                'id' => 'device_'.fake()->uuid(),
                'user_agent' => fake()->randomElement($userAgents),
            ];
        }

        return $devices;
    }

    private function printSummary(): void
    {
        $this->command->info("\n📊 Analytics Data Summary:");
        $this->command->info('Stories: '.Story::count());
        $this->command->info('Members: '.Member::count());
        $this->command->info('Story Views: '.number_format(StoryView::count()));
        $this->command->info('Interactions: '.number_format(MemberStoryInteraction::count()));
        $this->command->info('Ratings: '.number_format(MemberStoryRating::count()));
        $this->command->info('Reading History: '.number_format(MemberReadingHistory::count()));
        $this->command->info('Publishing Actions: '.number_format(StoryPublishingHistory::count()));
        $this->command->info("\n✨ Ready to view your analytics dashboard!");
    }
}
