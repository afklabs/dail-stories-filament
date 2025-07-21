<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\StoryPublishingHistory;
use Illuminate\Support\Facades\Cache;

class PublishingActivityWidget extends ChartWidget
{
    protected static ?string $heading = 'Publishing Activity (Last 14 Days)';
    protected static string $color = 'success';
    protected static ?string $pollingInterval = '300s';
    protected static bool $isLazy = true;
    protected static ?int $sort = 5;

    protected function getData(): array
    {
        try {
            return Cache::remember('publishing_activity_chart', 300, function () {
                $data = collect(range(13, 0))->map(function ($daysBack) {
                    $date = now()->subDays($daysBack);
                    
                    $activities = StoryPublishingHistory::whereDate('created_at', $date->toDateString())
                        ->selectRaw('action, COUNT(*) as count')
                        ->groupBy('action')
                        ->get()
                        ->pluck('count', 'action');
                    
                    return [
                        'date' => $date->format('M j'),
                        'published' => $activities['published'] ?? 0,
                        'updated' => $activities['updated'] ?? 0,
                        'unpublished' => $activities['unpublished'] ?? 0,
                        'scheduled' => $activities['scheduled'] ?? 0,
                    ];
                });

                return [
                    'datasets' => [
                        [
                            'label' => 'Published',
                            'data' => $data->pluck('published')->toArray(),
                            'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                            'borderColor' => 'rgb(16, 185, 129)',
                            'borderWidth' => 1,
                        ],
                        [
                            'label' => 'Updated',
                            'data' => $data->pluck('updated')->toArray(),
                            'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                            'borderColor' => 'rgb(59, 130, 246)',
                            'borderWidth' => 1,
                        ],
                        [
                            'label' => 'Scheduled',
                            'data' => $data->pluck('scheduled')->toArray(),
                            'backgroundColor' => 'rgba(245, 158, 11, 0.8)',
                            'borderColor' => 'rgb(245, 158, 11)',
                            'borderWidth' => 1,
                        ],
                        [
                            'label' => 'Unpublished',
                            'data' => $data->pluck('unpublished')->toArray(),
                            'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                            'borderColor' => 'rgb(239, 68, 68)',
                            'borderWidth' => 1,
                        ],
                    ],
                    'labels' => $data->pluck('date')->toArray(),
                ];
            });
        } catch (\Exception $e) {
            \Log::error('Publishing Activity Chart widget error', ['error' => $e->getMessage()]);

            return [
                'datasets' => [
                    [
                        'label' => 'Error',
                        'data' => array_fill(0, 14, 0),
                        'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                        'borderColor' => 'rgb(239, 68, 68)',
                    ],
                ],
                'labels' => collect(range(13, 0))->map(fn($d) => now()->subDays($d)->format('M j'))->toArray(),
            ];
        }
    }

    protected function getType(): string
    {
        return 'bar';
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
                    'stacked' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Date',
                    ],
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Actions',
                    ],
                ],
            ],
        ];
    }
}