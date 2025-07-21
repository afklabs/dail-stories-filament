<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\StoryRatingAggregate;
use Illuminate\Support\Facades\Cache;

class ContentQualityWidget extends ChartWidget
{
    protected static ?string $heading = 'Content Quality Distribution';
    protected static string $color = 'warning';
    protected static ?string $pollingInterval = '600s';
    protected static bool $isLazy = true;
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        try {
            return Cache::remember('content_quality_chart', 600, function () {
                $excellent = StoryRatingAggregate::where('average_rating', '>=', 4.5)->count();
                $good = StoryRatingAggregate::whereBetween('average_rating', [3.5, 4.49])->count();
                $average = StoryRatingAggregate::whereBetween('average_rating', [2.5, 3.49])->count();
                $poor = StoryRatingAggregate::whereBetween('average_rating', [1.5, 2.49])->count();
                $bad = StoryRatingAggregate::where('average_rating', '<', 1.5)->count();

                return [
                    'datasets' => [
                        [
                            'data' => [$excellent, $good, $average, $poor, $bad],
                            'backgroundColor' => [
                                '#059669', // excellent - dark green
                                '#10B981', // good - green
                                '#F59E0B', // average - yellow
                                '#F97316', // poor - orange
                                '#EF4444', // bad - red
                            ],
                            'borderWidth' => 2,
                            'borderColor' => '#ffffff',
                        ],
                    ],
                    'labels' => ['Excellent (4.5+)', 'Good (3.5-4.4)', 'Average (2.5-3.4)', 'Poor (1.5-2.4)', 'Bad (<1.5)'],
                ];
            });
        } catch (\Exception $e) {
            \Log::error('Content Quality widget error', ['error' => $e->getMessage()]);

            return [
                'datasets' => [
                    [
                        'data' => [1],
                        'backgroundColor' => ['#ef4444'],
                        'borderColor' => '#ffffff',
                    ],
                ],
                'labels' => ['Error loading data'],
            ];
        }
    }

    protected function getType(): string
    {
        return 'doughnut';
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
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 15,
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const label = context.label || "";
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return label + ": " + value + " stories (" + percentage + "%)";
                        }'
                    ],
                ],
            ],
            'cutout' => '40%',
        ];
    }
}