<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Categories'),
            
            'with_stories' => Tab::make('With Stories')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('stories'))
                ->badge(function () {
                    return static::getResource()::getModel()::whereHas('stories')->count();
                }),

            'active_stories' => Tab::make('With Active Stories')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereHas('stories', function (Builder $q) {
                        $q->where('active', true);
                    })
                )
                ->badge(function () {
                    return static::getResource()::getModel()::whereHas('stories', function (Builder $q) {
                        $q->where('active', true);
                    })->count();
                }),

            'empty' => Tab::make('Empty')
                ->modifyQueryUsing(fn (Builder $query) => $query->doesntHave('stories'))
                ->badge(function () {
                    return static::getResource()::getModel()::doesntHave('stories')->count();
                }),

            'popular' => Tab::make('Popular')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->withCount('stories')
                          ->having('stories_count', '>=', 5)
                )
                ->badge(function () {
                    return static::getResource()::getModel()::withCount('stories')
                        ->having('stories_count', '>=', 5)
                        ->count();
                }),
        ];
    }
}