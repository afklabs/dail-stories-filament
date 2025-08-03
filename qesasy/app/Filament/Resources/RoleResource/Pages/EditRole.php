<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Role')
                ->modalDescription(function () {
                    $usersCount = $this->record->users()->count();
                    if ($usersCount > 0) {
                        return "This role is assigned to {$usersCount} user(s). Deleting it will remove their permissions. Are you sure?";
                    }

                    return 'Are you sure you want to delete this role?';
                })
                ->modalSubmitActionLabel('Yes, delete role'),

            Actions\Action::make('duplicate')
                ->label('Duplicate Role')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->action(function () {
                    $newRole = $this->record->replicate();
                    $newRole->name = $this->record->name.'_copy';
                    $newRole->save();

                    // Copy permissions
                    $permissions = $this->record->permissions;
                    $newRole->permissions()->sync($permissions->pluck('id'));

                    Notification::make()
                        ->success()
                        ->title('Role duplicated')
                        ->body("Created a copy of '{$this->record->name}' as '{$newRole->name}'")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->button()
                                ->url(RoleResource::getUrl('edit', ['record' => $newRole])),
                        ])
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Duplicate Role')
                ->modalDescription('This will create a copy of this role with all its permissions.')
                ->modalSubmitActionLabel('Duplicate'),

            Actions\Action::make('manage_users')
                ->label('Manage Users')
                ->icon('heroicon-o-users')
                ->color('info')
                ->url(fn () => route('filament.admin.resources.users.index', [
                    'tableFilters[roles][values][0]' => $this->record->id,
                ]))
                ->openUrlInNewTab(),
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
        // Prevent changing system roles
        if (in_array($this->record->name, ['super_admin', 'admin'])) {
            // Don't allow changing the name of system roles
            $data['name'] = $this->record->name;
        } else {
            // Clean up the name for custom roles
            $data['name'] = strtolower(trim($data['name']));
            $data['name'] = preg_replace('/[^a-z0-9_]/', '_', $data['name']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Log role update
        activity()
            ->causedBy(auth()->user())
            ->performedOn($this->record)
            ->log('Role updated: '.$this->record->name);

        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function beforeSave(): void
    {
        // Validate that super_admin role always has all permissions
        if ($this->record->name === 'super_admin') {
            $allPermissions = \Spatie\Permission\Models\Permission::all();
            $this->data['permissions'] = $allPermissions->pluck('id')->toArray();
        }
    }
}
