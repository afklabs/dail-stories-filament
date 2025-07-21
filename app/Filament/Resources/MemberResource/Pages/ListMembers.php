<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Members'),
            
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge(function () {
                    return static::getResource()::getModel()::where('status', 'active')->count();
                }),

            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'inactive'))
                ->badge(function () {
                    return static::getResource()::getModel()::where('status', 'inactive')->count();
                }),

            'suspended' => Tab::make('Suspended')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'suspended'))
                ->badge(function () {
                    return static::getResource()::getModel()::where('status', 'suspended')->count();
                }),

            'recent' => Tab::make('Recent (30 days)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)))
                ->badge(function () {
                    return static::getResource()::getModel()::where('created_at', '>=', now()->subDays(30))->count();
                }),

        ];
    }
}