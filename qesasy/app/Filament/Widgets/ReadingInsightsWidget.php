<?php

namespace App\Filament\Widgets;

use App\Models\MemberReadingHistory;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReadingInsightsWidget extends ChartWidget
{
    protected static string $color = 'success';

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = true;

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    /**
     * Widget heading
     */
    public function getHeading(): string
    {
        return 'Reading Completion Distribution';
    }

    /**
     * Widget description
     */
    public function getDescription(): ?string
    {
        return 'Distribution of reading progress across all stories';
    }

    /**
     * Get chart data with error handling and optimization
     */
    protected function getData(): array
    {
        try {
            $completionData = Cache::remember('dashboard.reading_insights', 300, function () {
                return $this->getReadingCompletionData();
            });

            if (empty($completionData)) {
                return $this->getEmptyStateData();
            }

            return $this->formatChartData($completionData);
        } catch (\Exception $e) {
            Log::error('Dashboard ReadingInsights widget error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getErrorStateData();
        }
    }

    /**
     * Get reading completion data from database
     */
    private function getReadingCompletionData(): array
    {
        return MemberReadingHistory::select(
            DB::raw('CASE 
                WHEN reading_progress = 0 THEN "Not Started"
                WHEN reading_progress > 0 AND reading_progress < 25 THEN "Started (0-25%)"
                WHEN reading_progress >= 25 AND reading_progress < 50 THEN "Progress (25-50%)"
                WHEN reading_progress >= 50 AND reading_progress < 75 THEN "Halfway (50-75%)"
                WHEN reading_progress >= 75 AND reading_progress < 100 THEN "Almost Done (75-99%)"
                WHEN reading_progress = 100 THEN "Completed"
                ELSE "Unknown"
                END as completion_stage'),
            DB::raw('COUNT(*) as count')
        )
            ->whereNotNull('reading_progress')
            ->groupBy('completion_stage')
            ->orderByRaw('MIN(reading_progress)')
            ->pluck('count', 'completion_stage')
            ->toArray();
    }

    /**
     * Format chart data with proper structure
     */
    private function formatChartData(array $completionData): array
    {
        $stages = [
            'Not Started',
            'Started (0-25%)',
            'Progress (25-50%)',
            'Halfway (50-75%)',
            'Almost Done (75-99%)',
            'Completed',
        ];

        $colors = [
            '#ef4444', // Red - Not Started
            '#f97316', // Orange - Started
            '#eab308', // Yellow - Progress
            '#84cc16', // Lime - Halfway
            '#22c55e', // Green - Almost Done
            '#10b981', // Emerald - Completed
        ];

        $data = [];
        $actualLabels = [];
        $actualColors = [];

        foreach ($stages as $index => $stage) {
            $count = $completionData[$stage] ?? 0;
            if ($count > 0) {
                $data[] = $count;
                $actualLabels[] = $stage;
                $actualColors[] = $colors[$index];
            }
        }

        // If no data, show all stages with zero values
        if (empty($data)) {
            $data = array_fill(0, count($stages), 0);
            $actualLabels = $stages;
            $actualColors = $colors;
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $actualColors,
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                    'hoverBorderWidth' => 3,
                    'hoverBackgroundColor' => $actualColors,
                    'hoverOffset' => 6,
                ],
            ],
            'labels' => $actualLabels,
        ];
    }

    /**
     * Get empty state data when no records exist
     */
    private function getEmptyStateData(): array
    {
        return [
            'datasets' => [
                [
                    'data' => [1],
                    'backgroundColor' => ['#e5e7eb'],
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => ['No reading data available'],
        ];
    }

    /**
     * Get error state data when something goes wrong
     */
    private function getErrorStateData(): array
    {
        return [
            'datasets' => [
                [
                    'data' => [1],
                    'backgroundColor' => ['#ef4444'],
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => ['Error loading data'],
        ];
    }

    /**
     * Get chart type
     */
    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * Get chart options with enhanced configuration
     */
    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'animation' => [
                'animateRotate' => true,
                'animateScale' => true,
                'duration' => 1000,
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'align' => 'center',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'padding' => 20,
                        'font' => [
                            'size' => 12,
                            'weight' => '500',
                        ],
                        'color' => '#374151',
                        'generateLabels' => 'function(chart) {
                            const data = chart.data;
                            if (data.labels.length && data.datasets.length) {
                                return data.labels.map((label, i) => {
                                    const dataset = data.datasets[0];
                                    const backgroundColor = dataset.backgroundColor[i];
                                    const value = dataset.data[i];
                                    const total = dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    
                                    return {
                                        text: label + " (" + percentage + "%)",
                                        fillStyle: backgroundColor,
                                        strokeStyle: backgroundColor,
                                        lineWidth: 0,
                                        pointStyle: "circle",
                                        hidden: false,
                                        index: i
                                    };
                                });
                            }
                            return [];
                        }',
                    ],
                ],
                'tooltip' => [
                    'enabled' => true,
                    'backgroundColor' => 'rgba(0, 0, 0, 0.8)',
                    'titleColor' => '#ffffff',
                    'bodyColor' => '#ffffff',
                    'borderColor' => '#374151',
                    'borderWidth' => 1,
                    'cornerRadius' => 6,
                    'displayColors' => true,
                    'callbacks' => [
                        'title' => 'function(tooltipItems) {
                            return tooltipItems[0].label || "";
                        }',
                        'label' => 'function(context) {
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return "Stories: " + value + " (" + percentage + "%)";
                        }',
                    ],
                ],
            ],
            'cutout' => '60%',
            'radius' => '90%',
            'elements' => [
                'arc' => [
                    'borderAlign' => 'center',
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'nearest',
            ],
        ];
    }

    /**
     * Get additional chart filters/actions if needed
     */
    protected function getFilters(): ?array
    {
        return [
            'today' => 'Today',
            'week' => 'This Week',
            'month' => 'This Month',
            'all' => 'All Time',
        ];
    }

    /**
     * Apply filter to data
     */
    protected function getFilteredData(?string $filter): array
    {
        $query = MemberReadingHistory::query();

        match ($filter) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            default => null, // 'all' or null - no additional filtering
        };

        return $query->select(
            DB::raw('CASE 
                WHEN reading_progress = 0 THEN "Not Started"
                WHEN reading_progress > 0 AND reading_progress < 25 THEN "Started (0-25%)"
                WHEN reading_progress >= 25 AND reading_progress < 50 THEN "Progress (25-50%)"
                WHEN reading_progress >= 50 AND reading_progress < 75 THEN "Halfway (50-75%)"
                WHEN reading_progress >= 75 AND reading_progress < 100 THEN "Almost Done (75-99%)"
                WHEN reading_progress = 100 THEN "Completed"
                ELSE "Unknown"
                END as completion_stage'),
            DB::raw('COUNT(*) as count')
        )
            ->whereNotNull('reading_progress')
            ->groupBy('completion_stage')
            ->orderByRaw('MIN(reading_progress)')
            ->pluck('count', 'completion_stage')
            ->toArray();
    }

    /**
     * Check if widget should be displayed
     */
    public static function canView(): bool
    {
        return MemberReadingHistory::exists();
    }

    /**
     * Get widget stats for summary
     */
    public function getStats(): array
    {
        return Cache::remember('reading_insights.stats', 300, function () {
            $total = MemberReadingHistory::count();
            $completed = MemberReadingHistory::where('reading_progress', 100)->count();
            $inProgress = MemberReadingHistory::where('reading_progress', '>', 0)
                ->where('reading_progress', '<', 100)->count();

            return [
                'total_readings' => $total,
                'completed_readings' => $completed,
                'in_progress_readings' => $inProgress,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ];
        });
    }
}
