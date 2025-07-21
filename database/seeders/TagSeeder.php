<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tag;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    public function run()
    {
        $tags = [
            'trending',
            'breaking-news',
            'featured',
            'popular',
            'exclusive',
            'analysis',
            'review',
            'tutorial',
            'guide',
            'tips',
            'news',
            'update',
            'announcement',
            'interview',
            'research',
            'opinion',
            'debate',
            'discussion',
            'community',
            'viral',
            'how-to',
            'explainer',
            'investigation',
            'report',
            'study',
            'innovation',
            'startup',
            'ai',
            'climate',
            'covid-19',
        ];

        foreach ($tags as $tagName)
        {
            Tag::create([
                'name' => ucfirst(str_replace('-', ' ', $tagName)),
                'slug' => $tagName,
            ]);
        }
    }
}
