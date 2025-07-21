<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\{Story, StoryView, StoryRatingAggregate};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;

class StoryAnalyticsOverviewWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';
    protected static bool $isLazy = true;
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        try {
            return Cache::remember('story_analytics_overview', 300, function () {
                $totalStories = Story::count();
                $activeStories = Story::active()->count();
                $totalViews = StoryView::count();
                $todayViews = StoryView::whereDate('viewed_at', today())->count();
                $yesterdayViews = StoryView::whereDate('viewed_at', today()->subDay())->count();
                
                $viewsGrowth = $yesterdayViews > 0 
                    ? round((($todayViews - $yesterdayViews) / $yesterdayViews) * 100, 1)
                    : 0;

                $avgRating = StoryRatingAggregate::avg('average_rating') ?? 0;
                $totalRatings = StoryRatingAggregate::sum('total_ratings');

                return [
                    Stat::make('Active Stories', Number::format($activeStories))
                        ->description(Number::format($totalStories) . ' total stories')
                        ->descriptionIcon('heroicon-m-document-text')
                        ->color('primary'),

                    Stat::make('Total Views', Number::format($totalViews))
                        ->description(Number::format($todayViews) . ' today')
                        ->descriptionIcon($viewsGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                        ->color($viewsGrowth >= 0 ? 'success' : 'danger'),

                    Stat::make('Average Rating', number_format($avgRating, 2) . '/5')
                        ->description(Number::format($totalRatings) . ' total ratings')
                        ->descriptionIcon('heroicon-m-star')
                        ->color('warning'),

                    Stat::make('Today\'s Views', Number::format($todayViews))
                        ->description($viewsGrowth >= 0 ? "+{$viewsGrowth}% from yesterday" : "{$viewsGrowth}% from yesterday")
                        ->descriptionIcon($viewsGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                        ->color($viewsGrowth >= 0 ? 'success' : 'danger'),
                ];
            });
        } catch (\Exception $e) {
            \Log::error('Story Analytics Overview widget error', ['error' => $e->getMessage()]);
            
            return [
                Stat::make('Error', 'Unable to load data')
                    ->description('Check system logs')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }
    }
}