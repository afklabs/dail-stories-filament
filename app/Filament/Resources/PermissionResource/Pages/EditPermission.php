<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;

    /**
     * FIXED: Added proper type casting for PHPStan
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Permission $record */
        $record = $this->record;

        // Now PHPStan knows the exact type - no more warnings!
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Permission')
                ->modalDescription(function () {
                    /** @var Permission $record */
                    $record = $this->record;

                    $rolesCount = $record->roles()->count();
                    $usersCount = $record->users()->count();

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
                        ->options(Role::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    /** @var Permission $record */
                    $record = $this->record;

                    $role = Role::find($data['role_id']);
                    if ($role) {
                        $role->givePermissionTo($record);

                        Notification::make()
                            ->success()
                            ->title('Permission assigned')
                            ->body("Permission '{$record->name}' has been assigned to role '{$role->name}'")
                            ->send();
                    }
                }),

            Actions\Action::make('duplicate')
                ->label('Duplicate Permission')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\TextInput::make('new_name')
                        ->label('New Permission Name')
                        ->required()
                        ->default(function () {
                            /** @var Permission $record */
                            $record = $this->record;
                            return $record->name . '_copy';
                        })
                        ->rules(['regex:/^[a-z0-9_]+$/']),
                ])
                ->action(function (array $data) {
                    /** @var Permission $record */
                    $record = $this->record;

                    $newPermission = $record->replicate();
                    $newPermission->name = strtolower(trim($data['new_name']));
                    $newPermission->save();

                    // Copy role assignments
                    $roles = $record->roles;
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
                    /** @var Permission $record */
                    $record = $this->record;

                    $superAdminRole = Role::where('name', 'super_admin')->first();

                    if ($superAdminRole) {
                        $superAdminRole->givePermissionTo($record);

                        Notification::make()
                            ->success()
                            ->title('Permission added to Super Admin')
                            ->body("Permission '{$record->name}' has been added to Super Admin role")
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
        /** @var Permission $record */
        $record = $this->record;

        // Prevent changing system permissions
        $systemPermissions = [
            'view_any_user',
            'view_user',
            'create_user',
            'update_user',
            'delete_user',
            'view_any_role',
            'view_role',
            'create_role',
            'update_role',
            'delete_role',
            'view_any_permission',
            'view_permission',
            'create_permission',
            'update_permission',
            'delete_permission',
        ];

        if (in_array($record->name, $systemPermissions)) {
            // Don't allow changing the name of system permissions
            $data['name'] = $record->name;
            $data['guard_name'] = $record->guard_name;
        } else {
            // Clean up the name for custom permissions
            $data['name'] = strtolower(trim($data['name']));
            $data['name'] = preg_replace('/[^a-z0-9_]/', '_', $data['name']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Permission) {
            // Touch related models to update timestamps
            $record->roles()->touch();
            $record->users()->touch();

            // Log activity only if Spatie Activity Log is available
            if (function_exists('activity')) {
                try {
                    activity()
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->withProperties(['name' => $record->name])
                        ->log('Permission updated');
                } catch (\Exception $e) {
                    // Silently fail if activity logging is not available
                    \Illuminate\Support\Facades\Log::info('Activity logging failed: ' . $e->getMessage());
                }
            }

            // Clear cached permissions
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    protected function beforeSave(): void
    {
        /** @var Permission $record */
        $record = $this->record;

        // Ensure super_admin role always gets new permissions
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo($record)) {
            $superAdminRole->givePermissionTo($record);
        }
    }
}
