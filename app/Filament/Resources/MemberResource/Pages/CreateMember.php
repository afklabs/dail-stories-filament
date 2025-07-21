<?php

// app/Filament/Resources/MemberResource/Pages/CreateMember.php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Member created')
            ->body('The member has been created successfully.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default status if not provided
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }

        // Remove empty password field
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
