<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([

            CategorySeeder::class,
            TagSeeder::class,
            MemberSeeder::class,
            StorySeeder::class,
            StoryInteractionSeeder::class,
            StoryRatingSeeder::class,
            StoryAnalyticsSeeder::class,
        ]);

        // Optional: Run demo data seeder for development
        if (app()->environment('local', 'development'))
        {
            $this->call(DemoDataSeeder::class);
        }
    }
}
