<?php

// app/Filament/Resources/MemberResource/Pages/EditMember.php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Member updated')
            ->body('The member has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove empty password field
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
