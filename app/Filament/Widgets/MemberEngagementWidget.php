<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use App\Models\MemberStoryInteraction;
use App\Models\StoryView;
use Filament\Widgets\ChartWidget;

class MemberEngagementWidget extends ChartWidget
{
    protected static string $color = 'info';

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = true;

    public function getHeading(): string
    {
        return 'Member Engagement Trends (Last 7 Days)';
    }

    protected function getData(): array
    {
        try {
            $data = cache()->remember('dashboard.engagement_trends', 300, function () {
                return collect(range(6, 0))->map(function ($daysBack) {
                    $date = now()->subDays($daysBack);

                    return [
                        'date' => $date->format('M j'),
                        'views' => StoryView::whereDate('created_at', $date->toDateString())->count(),
                        'interactions' => MemberStoryInteraction::whereDate('created_at', $date->toDateString())->count(),
                        'logins' => Member::whereDate('last_login_at', $date->toDateString())->count(),
                    ];
                });
            });

            return [
                'datasets' => [
                    [
                        'label' => 'Story Views',
                        'data' => $data->pluck('views')->toArray(),
                        'borderColor' => 'rgb(59, 130, 246)',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'tension' => 0.2,
                        'fill' => true,
                    ],
                    [
                        'label' => 'Interactions',
                        'data' => $data->pluck('interactions')->toArray(),
                        'borderColor' => 'rgb(16, 185, 129)',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'tension' => 0.2,
                        'fill' => true,
                    ],
                    [
                        'label' => 'Daily Logins',
                        'data' => $data->pluck('logins')->toArray(),
                        'borderColor' => 'rgb(245, 158, 11)',
                        'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                        'tension' => 0.2,
                        'fill' => true,
                    ],
                ],
                'labels' => $data->pluck('date')->toArray(),
            ];
        } catch (\Exception $e) {
            \Log::error('Dashboard Engagement widget error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'datasets' => [
                    [
                        'label' => 'Error',
                        'data' => [0, 0, 0, 0, 0, 0, 0],
                        'borderColor' => 'rgb(239, 68, 68)',
                        'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    ],
                ],
                'labels' => ['Error loading data'],
            ];
        }
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Date',
                    ],
                ],
                'y' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Count',
                    ],
                    'beginAtZero' => true,
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
        ];
    }
}
