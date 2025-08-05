<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation(),
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
            ->title('Role updated')
            ->body('The role has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Clean up the name
        $data['name'] = strtolower(str_replace(' ', '_', trim($data['name'])));

        return $data;
    }

    protected function afterSave(): void
    {
        // Clear cached permissions and roles
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
