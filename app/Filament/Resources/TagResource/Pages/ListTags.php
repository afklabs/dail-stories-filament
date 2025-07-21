<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Filament\Resources\TagResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTags extends ListRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Tags'),
            
            'with_stories' => Tab::make('With Stories')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereHas('stories')
                )
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

            'unused' => Tab::make('Unused')
                ->modifyQueryUsing(fn (Builder $query) => $query->doesntHave('stories'))
                ->badge(function () {
                    return static::getResource()::getModel()::doesntHave('stories')->count();
                }),

            'popular' => Tab::make('Popular (3+)')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereHas('stories', null, '>=', 3)
                )
                ->badge(function () {
                    return static::getResource()::getModel()::whereHas('stories', null, '>=', 3)->count();
                }),

            'highly_used' => Tab::make('Highly Used (10+)')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereHas('stories', null, '>=', 10)
                )
                ->badge(function () {
                    return static::getResource()::getModel()::whereHas('stories', null, '>=', 10)->count();
                }),
        ];
    }
}