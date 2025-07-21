<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Latest tech news, gadgets, and innovations'
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Business insights, market trends, and entrepreneurship'
            ],
            [
                'name' => 'Health',
                'slug' => 'health',
                'description' => 'Health tips, medical news, and wellness advice'
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'description' => 'Sports news, scores, and athlete stories'
            ],
            [
                'name' => 'Entertainment',
                'slug' => 'entertainment',
                'description' => 'Movies, music, celebrities, and pop culture'
            ],
            [
                'name' => 'Science',
                'slug' => 'science',
                'description' => 'Scientific discoveries and research updates'
            ],
            [
                'name' => 'Politics',
                'slug' => 'politics',
                'description' => 'Political news and analysis'
            ],
            [
                'name' => 'Travel',
                'slug' => 'travel',
                'description' => 'Travel guides, destinations, and tips'
            ],
            [
                'name' => 'Food',
                'slug' => 'food',
                'description' => 'Recipes, restaurants, and food culture'
            ],
            [
                'name' => 'Education',
                'slug' => 'education',
                'description' => 'Educational content and learning resources'
            ],
            [
                'name' => 'Lifestyle',
                'slug' => 'lifestyle',
                'description' => 'Fashion, home, and lifestyle trends'
            ],
            [
                'name' => 'Environment',
                'slug' => 'environment',
                'description' => 'Environmental news and sustainability'
            ],
        ];

        foreach ($categories as $category)
        {
            Category::create($category);
        }
    }
}
