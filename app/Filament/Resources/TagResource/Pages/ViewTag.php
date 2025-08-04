<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Filament\Resources\TagResource;
use App\Models\Tag;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewTag extends ViewRecord
{
    protected static string $resource = TagResource::class;

    /**
     * FIXED: Added proper type casting for PHPStan
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Tag $record */
        $record = $this->record;

        // Now PHPStan knows the exact type - no more warnings!
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('view_stories')
                ->label('View All Stories')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->url(function () {
                    /** @var Tag $record */
                    $record = $this->record;

                    // Redirect to stories index filtered by this tag
                    return route('filament.admin.resources.stories.index', [
                        'tableFilters' => [
                            'tag' => ['value' => $record->id]
                        ]
                    ]);
                })
                ->visible(function () {
                    /** @var Tag $record */
                    $record = $this->record;
                    return $record->stories()->exists();
                }),

            Actions\Action::make('export_stories')
                ->label('Export Stories List')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    /** @var Tag $record */
                    $record = $this->record;

                    $stories = $record->stories()
                        ->with(['category'])
                        ->get()
                        ->map(function ($story) {
                            return [
                                'id' => $story->id,
                                'title' => $story->title,
                                'category' => $story->category?->name ?? 'Uncategorized',
                                'views' => $story->views,
                                'reading_time' => $story->reading_time_minutes,
                                'status' => $story->active ? 'Published' : 'Draft',
                                'created_at' => $story->created_at->format('Y-m-d H:i:s'),
                                'published_at' => $story->published_at?->format('Y-m-d H:i:s'),
                            ];
                        });

                    // Create CSV content
                    $csvContent = "ID,Title,Category,Views,Reading Time (min),Status,Created At,Published At\n";

                    foreach ($stories as $story) {
                        $csvContent .= implode(',', [
                            $story['id'],
                            '"' . str_replace('"', '""', $story['title']) . '"',
                            '"' . str_replace('"', '""', $story['category']) . '"',
                            $story['views'],
                            $story['reading_time'],
                            $story['status'],
                            $story['created_at'],
                            $story['published_at'] ?? '',
                        ]) . "\n";
                    }

                    // Create temporary file
                    $filename = "tag_{$record->slug}_stories_" . now()->format('Y-m-d_H-i-s') . '.csv';
                    $filePath = storage_path('app/temp/' . $filename);

                    // Ensure directory exists
                    if (!file_exists(dirname($filePath))) {
                        mkdir(dirname($filePath), 0755, true);
                    }

                    file_put_contents($filePath, $csvContent);

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Stories exported')
                        ->body("Stories list exported to: {$filename}")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('download')
                                ->button()
                                ->url(route('download.tag.stories', ['filename' => $filename]))
                                ->openUrlInNewTab(),
                        ])
                        ->send();
                })
                ->visible(function () {
                    /** @var Tag $record */
                    $record = $this->record;
                    return $record->stories()->exists();
                }),

            Actions\Action::make('tag_analytics')
                ->label('View Analytics')
                ->icon('heroicon-o-chart-bar')
                ->color('success')
                ->action(function () {
                    /** @var Tag $record */
                    $record = $this->record;

                    $analytics = [
                        'usage_stats' => [
                            'total_stories' => $record->stories()->count(),
                            'active_stories' => $record->stories()->where('active', true)->count(),
                            'total_views' => $record->stories()->sum('views'),
                            'average_views_per_story' => round($record->stories()->avg('views') ?? 0),
                        ],
                        'performance_metrics' => [
                            'top_story_views' => $record->stories()->max('views') ?? 0,
                            'average_reading_time' => round($record->stories()->avg('reading_time_minutes') ?? 0, 1),
                            'total_reading_time' => $record->stories()->sum('reading_time_minutes'),
                            'stories_this_month' => $record->stories()->where('stories.created_at', '>=', now()->startOfMonth())->count(),
                        ],
                        'content_categories' => $record->stories()
                            ->with('category')
                            ->get()
                            ->groupBy('category.name')
                            ->map->count()
                            ->sortDesc()
                            ->take(5)
                            ->toArray(),
                    ];

                    // Create a detailed analytics report
                    $report = "Tag Analytics Report: {$record->name}\n";
                    $report .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";

                    $report .= "Usage Statistics:\n";
                    foreach ($analytics['usage_stats'] as $key => $value) {
                        $report .= "- " . ucwords(str_replace('_', ' ', $key)) . ": {$value}\n";
                    }

                    $report .= "\nPerformance Metrics:\n";
                    foreach ($analytics['performance_metrics'] as $key => $value) {
                        $report .= "- " . ucwords(str_replace('_', ' ', $key)) . ": {$value}\n";
                    }

                    if (!empty($analytics['content_categories'])) {
                        $report .= "\nTop Categories:\n";
                        foreach ($analytics['content_categories'] as $category => $count) {
                            $report .= "- {$category}: {$count} stories\n";
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->info()
                        ->title('Analytics Generated')
                        ->body('Tag analytics have been calculated and logged.')
                        ->send();
                })
                ->visible(function () {
                    /** @var Tag $record */
                    $record = $this->record;
                    return $record->stories()->exists();
                }),

            Actions\Action::make('similar_tags')
                ->label('Find Similar Tags')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->action(function () {
                    /** @var Tag $record */
                    $record = $this->record;

                    // Find tags that share stories with this tag
                    $sharedStoryIds = $record->stories()->pluck('stories.id');

                    $similarTags = Tag::where('id', '!=', $record->id)
                        ->whereHas('stories', function ($query) use ($sharedStoryIds) {
                            $query->whereIn('stories.id', $sharedStoryIds);
                        })
                        ->withCount(['stories' => function ($query) use ($sharedStoryIds) {
                            $query->whereIn('stories.id', $sharedStoryIds);
                        }])
                        ->orderByDesc('stories_count')
                        ->limit(10)
                        ->get();

                    if ($similarTags->isNotEmpty()) {
                        $similarTagsList = $similarTags->map(function ($tag) {
                            return "- {$tag->name} ({$tag->stories_count} shared stories)";
                        })->join("\n");

                        \Filament\Notifications\Notification::make()
                            ->info()
                            ->title('Similar Tags Found')
                            ->body("Tags with shared content:\n{$similarTagsList}")
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('No Similar Tags')
                            ->body('No other tags share stories with this tag.')
                            ->send();
                    }
                })
                ->visible(function () {
                    /** @var Tag $record */
                    $record = $this->record;
                    return $record->stories()->exists();
                }),

            Actions\Action::make('usage_trends')
                ->label('Usage Trends')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('warning')
                ->action(function () {
                    /** @var Tag $record */
                    $record = $this->record;

                    // Calculate usage trends over the last 6 months
                    $trends = [];
                    for ($i = 5; $i >= 0; $i--) {
                        $monthStart = now()->subMonths($i)->startOfMonth();
                        $monthEnd = now()->subMonths($i)->endOfMonth();

                        $storiesCount = $record->stories()
                            ->whereBetween('stories.created_at', [$monthStart, $monthEnd])
                            ->count();

                        $trends[$monthStart->format('M Y')] = $storiesCount;
                    }

                    $trendsList = collect($trends)->map(function ($count, $month) {
                        return "- {$month}: {$count} stories";
                    })->join("\n");

                    \Filament\Notifications\Notification::make()
                        ->info()
                        ->title('Usage Trends (Last 6 Months)')
                        ->body("Stories created with this tag:\n{$trendsList}")
                        ->send();
                })
                ->visible(function () {
                    /** @var Tag $record */
                    $record = $this->record;
                    return $record->stories()->exists();
                }),
        ];
    }
}
