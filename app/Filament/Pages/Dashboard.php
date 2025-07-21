<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Cache;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.pages.organized-dashboard';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh_analytics')
                ->label('Refresh Analytics')
                ->icon('heroicon-o-arrow-path')
                ->size(ActionSize::Small)
                ->color('gray')
                ->action(function () {
                    Cache::flush();
                    $this->notify('success', 'Analytics data refreshed successfully');
                })
                ->tooltip('Refresh all dashboard data'),

            Action::make('view_detailed_analytics')
                ->label('Detailed Analytics')
                ->icon('heroicon-o-chart-bar-square')
                ->size(ActionSize::Small)
                ->color('primary')
                ->url(route('filament.admin.pages.analytics-dashboard'))
                ->tooltip('Open detailed analytics dashboard'),
        ];
    }

    public function getWidgets(): array
    {
        return [
            // Section 1: Overview Stats (Full Width)
            [
                \App\Filament\Widgets\StoryAnalyticsOverviewWidget::class,
                \App\Filament\Widgets\MemberOverviewWidget::class,
            ],

            // Section 2: Performance Charts (2 columns)
            [
                \App\Filament\Widgets\StoryPerformanceChartWidget::class,
                \App\Filament\Widgets\MemberEngagementWidget::class,
            ],

            // Section 3: Quality & Activity (2 columns)
            [
                \App\Filament\Widgets\ContentQualityWidget::class,
                \App\Filament\Widgets\PublishingActivityWidget::class,
            ],

            // Section 4: Data Tables (Full Width)
            \App\Filament\Widgets\TopStoriesTableWidget::class,

            // Section 5: Member Insights (3 columns)
            [
                \App\Filament\Widgets\ReadingInsightsWidget::class,
                \App\Filament\Widgets\MemberDemographicsWidget::class,
                \App\Filament\Widgets\TopMembersWidget::class,
            ],
        ];
    }

    public function getColumns(): int|string|array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 2,
            'xl' => 3,
        ];
    }
}
