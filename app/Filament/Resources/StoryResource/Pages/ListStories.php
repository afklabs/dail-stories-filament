<?php

namespace App\Filament\Resources\StoryResource\Pages;

use App\Filament\Resources\StoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStories extends ListRecords
{
    protected static string $resource = StoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Stories'),
            
            'published' => Tab::make('Published')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('active', true)
                    ->where(function ($q) {
                        $q->whereNull('active_from')->orWhere('active_from', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('active_until')->orWhere('active_until', '>=', now());
                    }))
                ->badge(function () {
                    return static::getResource()::getModel()::where('active', true)
                        ->where(function ($q) {
                            $q->whereNull('active_from')->orWhere('active_from', '<=', now());
                        })
                        ->where(function ($q) {
                            $q->whereNull('active_until')->orWhere('active_until', '>=', now());
                        })->count();
                }),

            'draft' => Tab::make('Drafts')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('active', false))
                ->badge(function () {
                    return static::getResource()::getModel()::where('active', false)->count();
                }),

            'scheduled' => Tab::make('Scheduled')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('active', true)
                    ->where('active_from', '>', now()))
                ->badge(function () {
                    return static::getResource()::getModel()::where('active', true)
                        ->where('active_from', '>', now())->count();
                }),

            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('active', true)
                    ->where('active_until', '<', now()))
                ->badge(function () {
                    return static::getResource()::getModel()::where('active', true)
                        ->where('active_until', '<', now())->count();
                }),
        ];
    }
}