<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class StorySeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        // Get all categories and tags
        $categories = Category::all();
        $tags = Tag::all();
        $authors = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['author', 'editor', 'admin', 'super-admin']);
        })->get();

        // Sample story titles by category
        $storyTitles = [
            'technology' => [
                'The Future of Artificial Intelligence in 2025',
                'Quantum Computing: Breaking New Barriers',
                'How 5G Technology is Transforming Industries',
                'The Rise of Blockchain Beyond Cryptocurrency',
                'Cybersecurity Trends Every Business Should Know',
            ],
            'business' => [
                'Startup Success: From Idea to IPO',
                'Remote Work Revolution: The New Normal',
                'Economic Forecast: What to Expect in 2025',
                'Small Business Guide to Digital Marketing',
                'The Impact of AI on Modern Business',
            ],
            'health' => [
                'Mental Health in the Digital Age',
                'Breakthrough in Cancer Research Shows Promise',
                'The Science Behind Healthy Aging',
                'Nutrition Myths Debunked by Experts',
                'Exercise Routines for Busy Professionals',
            ],
            'sports' => [
                'Champions League: Season Preview',
                'The Evolution of Sports Technology',
                'Rising Stars in World Athletics',
                'Olympic Games: Looking Ahead to 2028',
                'Fitness Trends That Actually Work',
            ],
        ];

        // Create stories for each category
        foreach ($categories as $category) {
            $categorySlug = $category->slug;
            $titles = $storyTitles[$categorySlug] ?? [];

            // Create specific stories if titles exist
            foreach ($titles as $index => $title) {
                $story = Story::create([
                    'title' => $title,
                    'content' => $this->generateStoryContent($faker, $title),
                    'excerpt' => $faker->paragraph(2),
                    'category_id' => $category->id,
                    'image' => $faker->imageUrl(800, 600, $categorySlug),
                    'views' => 0,
                    'active' => true,
                    'active_from' => now()->subDays(rand(1, 30)),
                    'active_until' => now()->addDays(rand(30, 90)),
                    'created_at' => now()->subDays(rand(1, 60)),
                ]);

                // Attach random tags
                $story->tags()->attach(
                    $tags->random(rand(2, 5))->pluck('id')->toArray()
                );
            }

            // Create additional random stories
            for ($i = 0; $i < rand(3, 5); $i++) {
                $story = Story::create([
                    'title' => $faker->sentence(rand(6, 10)),
                    'content' => $this->generateStoryContent($faker),
                    'excerpt' => $faker->paragraph(2),
                    'category_id' => $category->id,
                    'image' => $faker->optional(0.8)->imageUrl(800, 600, $categorySlug),
                    'views' => 0,
                    'active' => $faker->boolean(80), // 80% active
                    'active_from' => now()->subDays(rand(1, 60)),
                    'active_until' => now()->addDays(rand(15, 120)),
                    'created_at' => now()->subDays(rand(1, 90)),
                ]);

                // Attach random tags
                $story->tags()->attach(
                    $tags->random(rand(1, 4))->pluck('id')->toArray()
                );
            }
        }
    }

    private function generateStoryContent($faker, $title = null)
    {
        $paragraphs = [];

        // Opening paragraph
        if ($title) {
            $paragraphs[] = "In today's rapidly evolving world, ".
                strtolower(rtrim($title, '.')).
                ' represents a significant development that deserves our attention. '.
                'This comprehensive analysis explores the key aspects and implications.';
        } else {
            $paragraphs[] = $faker->paragraph(5);
        }

        // Body paragraphs
        for ($i = 0; $i < rand(4, 8); $i++) {
            $paragraphs[] = $faker->paragraph(rand(4, 8));
        }

        // Add some subheadings and content
        $subheadings = [
            'Key Developments',
            'Expert Analysis',
            'What This Means',
            'Looking Forward',
            'The Bottom Line',
        ];

        foreach (array_rand($subheadings, 3) as $index) {
            $paragraphs[] = "\n## ".$subheadings[$index]."\n";
            $paragraphs[] = $faker->paragraph(rand(3, 6));
            $paragraphs[] = $faker->paragraph(rand(3, 6));
        }

        // Conclusion
        $paragraphs[] = "\n## Conclusion\n";
        $paragraphs[] = $faker->paragraph(4);

        return implode("\n\n", $paragraphs);
    }
}
