<?php

namespace App\Filament\Resources\EmailCampaignResource\Pages;

use App\Filament\Resources\EmailCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmailCampaigns extends ListRecords
{
    protected static string $resource = EmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('حملة جديدة'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EmailCampaignResource\Widgets\CampaignStatsWidget::class,
        ];
    }
}
