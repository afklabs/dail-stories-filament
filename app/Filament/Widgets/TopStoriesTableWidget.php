<?php

namespace App\Filament\Widgets;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use App\Models\Story;
use Illuminate\Support\Number;

class TopStoriesTableWidget extends BaseTableWidget
{
    protected static ?string $heading = 'Top Performing Stories (Last 7 Days)';
    protected static ?int $sort = 4;
    protected static bool $isLazy = true;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Story::withCount([
                    'storyViews as recent_views' => function ($query) {
                        $query->where('viewed_at', '>=', now()->subDays(7));
                    },
                    'interactions as recent_interactions' => function ($query) {
                        $query->where('created_at', '>=', now()->subDays(7));
                    }
                ])
                ->with(['category', 'ratingAggregate'])
                ->where('active', true)
                ->orderByDesc('recent_views')
                ->limit(10)
            )
            ->columns([
                TextColumn::make('title')
                    ->limit(40)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 40) {
                            return null;
                        }
                        return $state;
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('recent_views')
                    ->label('Views (7d)')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => Number::format($state)),

                TextColumn::make('recent_interactions')
                    ->label('Interactions (7d)')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => Number::format($state)),

                TextColumn::make('ratingAggregate.average_rating')
                    ->label('Rating')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'No ratings';
                        return number_format($state, 1) . '/5';
                    })
                    ->badge()
                    ->color(function ($state) {
                        if (!$state) return 'gray';
                        if ($state >= 4.5) return 'success';
                        if ($state >= 3.5) return 'warning';
                        return 'danger';
                    }),

                TextColumn::make('ratingAggregate.total_ratings')
                    ->label('# Ratings')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => Number::format($state ?? 0)),
            ])
            ->defaultSort('recent_views', 'desc')
            ->striped()
            ->paginated(false);
    }
}