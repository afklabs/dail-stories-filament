<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MemberDemographicsWidget extends ChartWidget
{
    protected static string $color = 'warning';
    protected static ?string $pollingInterval = '600s';
    protected static bool $isLazy = true;

    public function getHeading(): string
    {
        return 'Member Age Distribution';
    }

    protected function getData(): array
    {
        try {
            $ageGroups = cache()->remember('dashboard.member_demographics', 600, function() {
                return Member::select(
                    DB::raw('CASE 
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN "Under 18"
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 24 THEN "18-24"
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 25 AND 34 THEN "25-34"
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 35 AND 44 THEN "35-44"
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 45 AND 54 THEN "45-54"
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= 55 THEN "55+"
                        ELSE "Unknown"
                        END as age_group'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('age_group')
                ->pluck('count', 'age_group')
                ->toArray();
            });

            $expectedGroups = [
                'Under 18',
                '18-24',
                '25-34',
                '35-44',
                '45-54',
                '55+',
                'Unknown'
            ];

            $data = [];
            $labels = [];
            $colors = [
                '#3b82f6', // Blue
                '#8b5cf6', // Purple
                '#06b6d4', // Cyan
                '#10b981', // Emerald
                '#f59e0b', // Amber
                '#ef4444', // Red
                '#6b7280'  // Gray
            ];

            $finalColors = [];
            
            foreach ($expectedGroups as $group) {
                if (isset($ageGroups[$group]) && $ageGroups[$group] > 0) {
                    $data[] = $ageGroups[$group];
                    $labels[] = $group;
                    $finalColors[] = $colors[array_search($group, $expectedGroups)];
                }
            }

            if (empty($data)) {
                return [
                    'datasets' => [
                        [
                            'data' => [1],
                            'backgroundColor' => ['#6b7280'],
                            'borderColor' => '#ffffff',
                        ],
                    ],
                    'labels' => ['No age data available'],
                ];
            }

            return [
                'datasets' => [
                    [
                        'data' => $data,
                        'backgroundColor' => $finalColors,
                        'borderWidth' => 2,
                        'borderColor' => '#ffffff',
                        'hoverBorderWidth' => 3,
                    ],
                ],
                'labels' => $labels,
            ];
        } catch (\Exception $e) {
            \Log::error('Dashboard Demographics widget error', [
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
        return 'pie';
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
                        'font' => [
                            'size' => 12,
                        ],
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const label = context.label || "";
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return label + ": " + value + " members (" + percentage + "%)";
                        }'
                    ],
                ],
            ],
            'radius' => '80%',
        ];
    }
}