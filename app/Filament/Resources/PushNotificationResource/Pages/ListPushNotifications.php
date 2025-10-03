<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Resources\PushNotificationResource;
use App\Models\PushNotification;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPushNotifications extends ListRecords
{
    protected static string $resource = PushNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Notification')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Notifications')
                ->badge(PushNotification::count()),

            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', PushNotification::STATUS_DRAFT))
                ->badge(PushNotification::where('status', PushNotification::STATUS_DRAFT)->count())
                ->badgeColor('secondary'),

            'scheduled' => Tab::make('Scheduled')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', PushNotification::STATUS_SCHEDULED))
                ->badge(PushNotification::where('status', PushNotification::STATUS_SCHEDULED)->count())
                ->badgeColor('warning'),

            'sent' => Tab::make('Sent')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', PushNotification::STATUS_SENT))
                ->badge(PushNotification::where('status', PushNotification::STATUS_SENT)->count())
                ->badgeColor('success'),

            'failed' => Tab::make('Failed')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', PushNotification::STATUS_FAILED))
                ->badge(PushNotification::where('status', PushNotification::STATUS_FAILED)->count())
                ->badgeColor('danger'),
        ];
    }
}
