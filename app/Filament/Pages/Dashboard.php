<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.pages.dashboard';

    /**
     * @return array<class-string<\Filament\Widgets\Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\StoryAnalyticsOverviewWidget::class,
            \App\Filament\Widgets\MemberOverviewWidget::class,
            \App\Filament\Widgets\StoryPerformanceChartWidget::class,
            \App\Filament\Widgets\MemberEngagementWidget::class,
            \App\Filament\Widgets\ContentQualityWidget::class,
            \App\Filament\Widgets\PublishingActivityWidget::class,
            \App\Filament\Widgets\TopStoriesTableWidget::class,
            \App\Filament\Widgets\ReadingInsightsWidget::class,
            \App\Filament\Widgets\MemberDemographicsWidget::class,
            \App\Filament\Widgets\TopMembersWidget::class,
        ];
    }
}