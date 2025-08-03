<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MemberReadingHistory;
use App\Models\Story;
use App\Models\StoryPublishingHistory;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        $this->command->info('🌱 Starting Demo Data Seeding...');

        // Add reading history
        $this->seedReadingHistory();

        // Add publishing history
        $this->seedPublishingHistory();

        $this->command->info('✅ Demo Data Seeding completed successfully!');
    }

    private function seedReadingHistory()
    {
        $this->command->info('📖 Seeding Reading History...');

        $stories = Story::where('active', true)->get();
        $members = Member::where('status', 'active')->get();

        if ($members->isEmpty() || $stories->isEmpty()) {
            $this->command->info('⚠️ No active members or stories found, skipping reading history...');
            return;
        }

        $created = 0;
        $updated = 0;

        foreach ($members->random(min(20, $members->count())) as $member) {
            $readStories = $stories->random(rand(5, 15));

            foreach ($readStories as $story) {
                // Use updateOrCreate to handle existing records
                $history = MemberReadingHistory::updateOrCreate(
                    [
                        'member_id' => $member->id,
                        'story_id' => $story->id,
                    ],
                    [
                        'reading_progress' => rand(10, 100),
                        'time_spent' => rand(30, 600), // 30 seconds to 10 minutes
                        'last_read_at' => now()->subDays(rand(0, 30)),
                        'reading_sessions' => rand(1, 5),
                        'metadata' => [
                            'device_type' => \Faker\Factory::create()->randomElement(['mobile', 'tablet', 'desktop']),
                            'source' => 'demo_seeder',
                        ],
                    ]
                );

                if ($history->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        }

        $this->command->info("✓ Reading History seeded - Created: {$created}, Updated: {$updated}");
    }

    private function seedPublishingHistory()
    {
        $this->command->info('📅 Seeding Publishing History...');

        $stories = Story::all();
        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['admin', 'super_admin', 'super-admin', 'editor']);
        })->get();

        if ($admins->isEmpty()) {
            $this->command->info('⚠️ No admin users found, skipping publishing history...');
            return;
        }

        $created = 0;

        foreach ($stories->random(min(10, $stories->count())) as $story) {
            // Check if publishing history already exists for this story
            $existingHistory = StoryPublishingHistory::where('story_id', $story->id)
                ->where('action', 'published')
                ->first();

            if (!$existingHistory) {
                StoryPublishingHistory::create([
                    'story_id' => $story->id,
                    'user_id' => $admins->random()->id,
                    'action' => 'published',
                    'previous_active_status' => false,
                    'new_active_status' => true,
                    'new_active_from' => $story->active_from,
                    'new_active_until' => $story->active_until,
                    'notes' => 'Initial publication via demo seeder',
                    'ip_address' => '127.0.0.1',
                    'created_at' => $story->created_at,
                    'updated_at' => $story->created_at,
                ]);
                $created++;
            }
        }

        $this->command->info("✓ Publishing History seeded - Created: {$created} new records");
    }
}
