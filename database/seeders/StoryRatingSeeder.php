<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MemberStoryRating;
use App\Models\Story;
use App\Models\StoryRatingAggregate;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class StoryRatingSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        $stories = Story::where('active', true)->get();
        $members = Member::where('status', 'active')->get();

        if ($members->isEmpty() || $stories->isEmpty()) {
            return;
        }

        $ratingComments = [
            5 => [
                'Excellent story! Very informative.',
                'Great read, loved every bit of it!',
                'Amazing content, keep it up!',
                'This is exactly what I was looking for.',
                'Brilliant article, well written!',
            ],
            4 => [
                'Good story, quite helpful.',
                'Nice read, learned something new.',
                'Well written, minor improvements needed.',
                'Solid content, thanks for sharing.',
            ],
            3 => [
                'Average story, could be better.',
                'Okay content, nothing special.',
                'Decent read, but needs more depth.',
            ],
            2 => [
                'Not very helpful, disappointed.',
                'Below average, needs improvement.',
                'Could be much better.',
            ],
            1 => [
                'Poor quality content.',
                'Not what I expected.',
                'Needs major improvements.',
            ],
        ];

        foreach ($stories as $story) {
            // 20-60% of viewers rate the story
            $viewers = $members->random(rand(intval($members->count() * 0.2), intval($members->count() * 0.6)));

            foreach ($viewers as $member) {
                // Generate weighted random rating (more 4s and 5s)
                $weights = [1 => 5, 2 => 10, 3 => 20, 4 => 35, 5 => 30];
                $rating = $this->getWeightedRandom($weights);

                // 30% chance of leaving a comment for ratings
                $hasComment = $faker->boolean(30);
                $comment = $hasComment ? $faker->randomElement($ratingComments[$rating]) : null;

                MemberStoryRating::create([
                    'member_id' => $member->id,
                    'story_id' => $story->id,
                    'rating' => $rating,
                    'comment' => $comment,
                    'created_at' => $faker->dateTimeBetween($story->created_at, 'now'),
                ]);
            }

            // Update rating aggregate
            $this->updateStoryRatingAggregate($story->id);
        }
    }

    private function getWeightedRandom($weights)
    {
        $rand = rand(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            $rand -= $weight;
            if ($rand <= 0) {
                return $value;
            }
        }

        return array_key_last($weights);
    }

    private function updateStoryRatingAggregate($storyId)
    {
        $ratings = MemberStoryRating::where('story_id', $storyId)->get();

        if ($ratings->isEmpty()) {
            return;
        }

        $totalRatings = $ratings->count();
        $sumRatings = $ratings->sum('rating');
        $averageRating = round($sumRatings / $totalRatings, 2);

        // Calculate distribution
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = $ratings->where('rating', $i)->count();
        }

        StoryRatingAggregate::updateOrCreate(
            ['story_id' => $storyId],
            [
                'total_ratings' => $totalRatings,
                'sum_ratings' => $sumRatings,
                'average_rating' => $averageRating,
                'rating_distribution' => json_encode($distribution),
            ]
        );
    }
}
