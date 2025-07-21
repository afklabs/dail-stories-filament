<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\{StoryView, MemberStoryInteraction};
use Illuminate\Support\Facades\Cache;

class StoryPerformanceChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Story Performance (Last 30 Days)';
    protected static string $color = 'info';
    protected static ?string $pollingInterval = '300s';
    protected static bool $isLazy = true;
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        try {
            return Cache::remember('story_performance_chart', 300, function () {
                $data = collect(range(29, 0))->map(function ($daysBack) {
                    $date = now()->subDays($daysBack);
                    
                    return [
                        'date' => $date->format('M j'),
                        'views' => StoryView::whereDate('viewed_at', $date->toDateString())->count(),
                        'interactions' => MemberStoryInteraction::whereDate('created_at', $date->toDateString())->count(),
                        'unique_viewers' => StoryView::whereDate('viewed_at', $date->toDateString())
                            ->distinct('device_id')->count(),
                    ];
                });

                return [
                    'datasets' => [
                        [
                            'label' => 'Views',
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
                            'label' => 'Unique Viewers',
                            'data' => $data->pluck('unique_viewers')->toArray(),
                            'borderColor' => 'rgb(245, 158, 11)',
                            'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                            'tension' => 0.2,
                            'fill' => true,
                        ],
                    ],
                    'labels' => $data->pluck('date')->toArray(),
                ];
            });
        } catch (\Exception $e) {
            \Log::error('Story Performance Chart widget error', ['error' => $e->getMessage()]);

            return [
                'datasets' => [
                    [
                        'label' => 'Error',
                        'data' => array_fill(0, 30, 0),
                        'borderColor' => 'rgb(239, 68, 68)',
                        'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    ],
                ],
                'labels' => collect(range(29, 0))->map(fn($d) => now()->subDays($d)->format('M j'))->toArray(),
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
        ];
    }
}