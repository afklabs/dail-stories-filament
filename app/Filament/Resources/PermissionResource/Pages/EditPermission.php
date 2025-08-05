<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Permission;

class EditPermission extends EditRecord
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
            ->title('Permission updated')
            ->body('The permission has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Clean up the name
        $data['name'] = strtolower(str_replace(' ', '_', trim($data['name'])));

        return $data;
    }

    protected function afterSave(): void
    {
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
