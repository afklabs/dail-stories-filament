<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Story;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Member;
use App\Models\MemberReadingHistory;
use App\Models\StoryPublishingHistory;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;

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

        foreach ($members->random(min(20, $members->count())) as $member)
        {
            $readStories = $stories->random(rand(5, 15));

            foreach ($readStories as $story)
            {
                MemberReadingHistory::create([
                    'member_id' => $member->id,
                    'story_id' => $story->id,
                    'reading_progress' => rand(10, 100),
                    'time_spent' => rand(30, 600), // 30 seconds to 10 minutes
                    'last_read_at' => now()->subDays(rand(0, 30)),
                ]);
            }
        }

        $this->command->info('✓ Reading History seeded');
    }

    private function seedPublishingHistory()
    {
        $this->command->info('📅 Seeding Publishing History...');

        $stories = Story::all();
        $admins = User::whereHas('roles', function ($q)
        {
            $q->whereIn('name', ['admin', 'super-admin', 'editor']);
        })->get();

        foreach ($stories->random(min(10, $stories->count())) as $story)
        {
            StoryPublishingHistory::create([
                'story_id' => $story->id,
                'user_id' => $admins->random()->id,
                'action' => 'published',
                'previous_active_status' => false,
                'new_active_status' => true,
                'new_active_from' => $story->active_from,
                'new_active_until' => $story->active_until,
                'notes' => 'Initial publication',
                'ip_address' => '127.0.0.1',
                'created_at' => $story->created_at,
            ]);
        }

        $this->command->info('✓ Publishing History seeded');
    }
}
