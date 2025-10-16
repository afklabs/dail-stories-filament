<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailLogResource\Pages;

use App\Filament\Resources\EmailLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailLog extends ViewRecord
{
    protected static string $resource = EmailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resend')
                ->label('إعادة الإرسال')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->status === 'failed')
                ->action(function () {
                    \Filament\Notifications\Notification::make()
                        ->title('جاري إعادة الإرسال')
                        ->body('سيتم إعادة إرسال البريد الإلكتروني قريباً')
                        ->success()
                        ->send();
                }),
        ];
    }
}
