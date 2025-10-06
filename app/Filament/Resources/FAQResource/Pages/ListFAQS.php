<?php
// app/Filament/Resources/FAQResource/Pages/ListFAQS.php

namespace App\Filament\Resources\FAQResource\Pages;

use App\Filament\Resources\FAQResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFAQS extends ListRecords
{
    protected static string $resource = FAQResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New FAQ')
                ->icon('heroicon-o-plus'),
        ];
    }
}
