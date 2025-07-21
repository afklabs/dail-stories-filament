<?php

namespace App\Filament\Widgets;

use App\Models\MemberReadingHistory;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ReadingInsightsWidget extends ChartWidget
{
    protected static string $color = 'success';
    protected static ?string $pollingInterval = '300s';
    protected static bool $isLazy = true;

    public function getHeading(): string
    {
        return 'Reading Completion Distribution';
    }

    protected function getData(): array
    {
        try {
            $completionData = cache()->remember('dashboard.reading_insights', 300, function() {
                return MemberReadingHistory::select(
                    DB::raw('CASE 
                        WHEN reading_progress = 0 THEN "Not Started"
                        WHEN reading_progress > 0 AND reading_progress < 25 THEN "Started (0-25%)"
                        WHEN reading_progress >= 25 AND reading_progress < 50 THEN "Progress (25-50%)"
                        WHEN reading_progress >= 50 AND reading_progress < 75 THEN "Halfway (50-75%)"
                        WHEN reading_progress >= 75 AND reading_progress < 100 THEN "Almost Done (75-99%)"
                        WHEN reading_progress = 100 THEN "Completed"
                        END as completion_stage'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('completion_stage')
                ->pluck('count', 'completion_stage')
                ->toArray();
            });

            $stages = [
                'Not Started', 
                'Started (0-25%)', 
                'Progress (25-50%)', 
                'Halfway (50-75%)', 
                'Almost Done (75-99%)', 
                'Completed'
            ];

            $data = [];
            $colors = [
                '#ef4444', // Red
                '#f97316', // Orange
                '#eab308', // Yellow
                '#84cc16', // Lime
                '#22c55e', // Green
                '#10b981'  // Emerald
            ];

            foreach ($stages as $stage) {
                $data[] = $completionData[$stage] ?? 0;
            }

            return [
                'datasets' => [
                    [
                        'data' => $data,
                        'backgroundColor' => $colors,
                        'borderWidth' => 2,
                        'borderColor' => '#ffffff',
                        'hoverBorderWidth' => 3,
                    ],
                ],
                'labels' => $stages,
            ];
        } catch (\Exception $e) {
            \Log::error('Dashboard ReadingInsights widget error', [
                'error' => $e->getMessage()
            ]);

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
                        'padding' => 20,
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const label = context.label || "";
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return label + ": " + value + " (" + percentage + "%)";
                        }'
                    ],
                ],
            ],
            'cutout' => '50%',
            'radius' => '80%',
        ];
    }
}