<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * ✅ FIXED: Added proper type hint for getRecord()
     */
    public function getRecord(): Role
    {
        /** @var Role $record */
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
            ->title('Role created')
            ->body('The role has been created successfully.');
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
        // Clear cached permissions and roles
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
