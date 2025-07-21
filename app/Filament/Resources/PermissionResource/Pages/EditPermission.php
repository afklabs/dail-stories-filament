<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Permission')
                ->modalDescription(function () {
                    $rolesCount = $this->record->roles()->count();
                    $usersCount = $this->record->users()->count();
                    
                    if ($rolesCount > 0 || $usersCount > 0) {
                        return "This permission is assigned to {$rolesCount} role(s) and {$usersCount} user(s). Deleting it will remove their access. Are you sure?";
                    }
                    return 'Are you sure you want to delete this permission?';
                })
                ->modalSubmitActionLabel('Yes, delete permission'),

            Actions\Action::make('assign_to_role')
                ->label('Assign to Role')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Select::make('role_id')
                        ->label('Select Role')
                        ->options(\Spatie\Permission\Models\Role::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $role = \Spatie\Permission\Models\Role::find($data['role_id']);
                    $role->givePermissionTo($this->record);
                    
                    Notification::make()
                        ->success()
                        ->title('Permission assigned')
                        ->body("Permission '{$this->record->name}' has been assigned to role '{$role->name}'")
                        ->send();
                }),

            Actions\Action::make('duplicate')
                ->label('Duplicate Permission')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\TextInput::make('new_name')
                        ->label('New Permission Name')
                        ->required()
                        ->default($this->record->name . '_copy')
                        ->rules(['regex:/^[a-z0-9_]+$/']),
                ])
                ->action(function (array $data) {
                    $newPermission = $this->record->replicate();
                    $newPermission->name = strtolower(trim($data['new_name']));
                    $newPermission->save();
                    
                    // Copy role assignments
                    $roles = $this->record->roles;
                    $newPermission->roles()->sync($roles->pluck('id'));
                    
                    Notification::make()
                        ->success()
                        ->title('Permission duplicated')
                        ->body("Created a copy as '{$newPermission->name}'")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->button()
                                ->url(PermissionResource::getUrl('edit', ['record' => $newPermission])),
                        ])
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Duplicate Permission')
                ->modalDescription('This will create a copy of this permission with all its role assignments.')
                ->modalSubmitActionLabel('Duplicate'),

            Actions\Action::make('sync_to_super_admin')
                ->label('Add to Super Admin')
                ->icon('heroicon-o-star')
                ->color('warning')
                ->action(function () {
                    $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'super_admin')->first();
                    
                    if ($superAdminRole) {
                        $superAdminRole->givePermissionTo($this->record);
                        
                        Notification::make()
                            ->success()
                            ->title('Permission added to Super Admin')
                            ->body("Permission '{$this->record->name}' has been added to Super Admin role")
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('Super Admin role not found')
                            ->body('Could not find Super Admin role to assign this permission')
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Add to Super Admin')
                ->modalDescription('This will ensure the Super Admin role has this permission.')
                ->modalSubmitActionLabel('Add Permission'),
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
        // Prevent changing system permissions
        $systemPermissions = [
            'view_any_user', 'view_user', 'create_user', 'update_user', 'delete_user',
            'view_any_role', 'view_role', 'create_role', 'update_role', 'delete_role',
            'view_any_permission', 'view_permission', 'create_permission', 'update_permission', 'delete_permission'
        ];
        
        if (in_array($this->record->name, $systemPermissions)) {
            // Don't allow changing the name of system permissions
            $data['name'] = $this->record->name;
            $data['guard_name'] = $this->record->guard_name;
        } else {
            // Clean up the name for custom permissions
            $data['name'] = strtolower(trim($data['name']));
            $data['name'] = preg_replace('/[^a-z0-9_]/', '_', $data['name']);
        }
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Log permission update
        activity()
            ->causedBy(auth()->user())
            ->performedOn($this->record)
            ->log('Permission updated: ' . $this->record->name);

        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function beforeSave(): void
    {
        // Ensure super_admin role always gets new permissions
        $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'super_admin')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo($this->record)) {
            $superAdminRole->givePermissionTo($this->record);
        }
    }
}