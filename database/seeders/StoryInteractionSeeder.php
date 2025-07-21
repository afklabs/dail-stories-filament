<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Story;
use App\Models\Member;
use App\Models\MemberStoryInteraction;
use App\Models\StoryView;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class StoryInteractionSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        $stories = Story::where('active', true)->get();
        $members = Member::where('status', 'active')->get();

        if ($members->isEmpty() || $stories->isEmpty())
        {
            return;
        }

        foreach ($stories as $story)
        {
            $viewCount = rand(10, 500);
            $memberViewCount = intval($viewCount * 0.3);
            $viewingMembers = $members->random(min($memberViewCount, $members->count()));

            foreach ($viewingMembers as $member)
            {
                $sessionId = Str::random(16);
                $deviceId = strtolower(Str::random(16));

                StoryView::create([
                    'story_id' => $story->id,
                    'member_id' => $member->id,
                    'device_id' => $deviceId,
                    'session_id' => $sessionId,
                    'user_agent' => $faker->userAgent,
                    'ip_address' => $faker->ipv4,
                    'referrer' => $faker->optional(0.3)->url,
                    'metadata' => [
                        'platform' => $faker->randomElement(['web', 'mobile', 'tablet']),
                        'browser' => $faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
                    ],
                    'viewed_at' => $faker->dateTimeBetween($story->created_at, 'now'),
                ]);

                MemberStoryInteraction::firstOrCreate([
                    'member_id' => $member->id,
                    'story_id' => $story->id,
                    'action' => 'view',
                ]);
            }

            for ($i = 0; $i < ($viewCount - $memberViewCount); $i++)
            {
                StoryView::create([
                    'story_id' => $story->id,
                    'member_id' => null,
                    'device_id' => strtolower(Str::random(16)),
                    'session_id' => Str::random(16),
                    'user_agent' => $faker->userAgent,
                    'ip_address' => $faker->ipv4,
                    'referrer' => $faker->optional(0.3)->url,
                    'metadata' => [
                        'platform' => $faker->randomElement(['web', 'mobile', 'tablet']),
                        'browser' => $faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
                    ],
                    'viewed_at' => $faker->dateTimeBetween($story->created_at, 'now'),
                ]);
            }

            $story->update(['views' => StoryView::where('story_id', $story->id)->count()]);

            $bookmarkCount = rand(intval($memberViewCount * 0.05), intval($memberViewCount * 0.15));
            $bookmarkingMembers = $viewingMembers->random(min($bookmarkCount, $viewingMembers->count()));

            foreach ($bookmarkingMembers as $member)
            {
                MemberStoryInteraction::firstOrCreate([
                    'member_id' => $member->id,
                    'story_id' => $story->id,
                    'action' => 'bookmark',
                ]);
            }

            $shareCount = rand(intval($memberViewCount * 0.02), intval($memberViewCount * 0.08));
            $sharingMembers = $viewingMembers->random(min($shareCount, $viewingMembers->count()));

            foreach ($sharingMembers as $member)
            {
                MemberStoryInteraction::firstOrCreate([
                    'member_id' => $member->id,
                    'story_id' => $story->id,
                    'action' => 'share',
                ]);
            }
        }
    }
}
