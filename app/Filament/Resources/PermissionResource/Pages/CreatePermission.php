<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Permission;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;

    /**
     * ✅ FIXED: Added proper type hint for getRecord()
     */
    public function getRecord(): Permission
    {
        /** @var Permission $record */
        $record = parent::getRecord();
        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Permission created')
            ->body('The permission has been created successfully.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Always set guard_name to 'web' (hidden from user)
        $data['guard_name'] = 'web';

        // Clean up the name
        $data['name'] = strtolower(str_replace(' ', '_', trim($data['name'])));

        return $data;
    }

    protected function afterCreate(): void
    {
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
