<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Permission;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;

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
        // Ensure guard_name is set
        $data['guard_name'] = $data['guard_name'] ?? 'web';

        // Clean up the name
        $data['name'] = strtolower(trim($data['name']));
        $data['name'] = preg_replace('/[^a-z0-9_]/', '_', $data['name']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Log permission creation
        activity()
            ->causedBy(auth()->user())
            ->performedOn($this->record)
            ->log('Permission created: '.$this->record->name);

        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('bulk_create_permissions')
                ->label('Bulk Create Permissions')
                ->icon('heroicon-o-squares-plus')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\TextInput::make('resource_name')
                        ->label('Resource Name')
                        ->required()
                        ->placeholder('e.g., stories, members, categories')
                        ->helperText('Name of the resource (singular form)'),

                    \Filament\Forms\Components\CheckboxList::make('permission_types')
                        ->label('Permission Types')
                        ->options([
                            'view_any' => 'View Any (List all)',
                            'view' => 'View (View single)',
                            'create' => 'Create',
                            'update' => 'Update/Edit',
                            'delete' => 'Delete',
                            'delete_any' => 'Delete Any (Bulk delete)',
                            'restore' => 'Restore',
                            'restore_any' => 'Restore Any',
                            'force_delete' => 'Force Delete',
                            'force_delete_any' => 'Force Delete Any',
                        ])
                        ->default(['view_any', 'view', 'create', 'update', 'delete'])
                        ->required()
                        ->columns(2),

                    \Filament\Forms\Components\Select::make('category')
                        ->label('Category')
                        ->options([
                            'stories' => 'Stories',
                            'members' => 'Members',
                            'categories' => 'Categories',
                            'tags' => 'Tags',
                            'users' => 'Users',
                            'roles' => 'Roles',
                            'permissions' => 'Permissions',
                            'analytics' => 'Analytics',
                            'system' => 'System',
                            'settings' => 'Settings',
                        ])
                        ->default('stories'),
                ])
                ->action(function (array $data) {
                    $resourceName = strtolower(trim($data['resource_name']));
                    $permissionTypes = $data['permission_types'];
                    $category = $data['category'];

                    $created = 0;
                    $errors = [];

                    foreach ($permissionTypes as $type) {
                        $permissionName = "{$type}_{$resourceName}";

                        try {
                            $permission = Permission::firstOrCreate([
                                'name' => $permissionName,
                                'guard_name' => 'web',
                            ], [
                                'category' => $category,
                            ]);

                            if ($permission->wasRecentlyCreated) {
                                $created++;
                            }
                        } catch (\Exception $e) {
                            $errors[] = "Failed to create {$permissionName}: ".$e->getMessage();
                        }
                    }

                    if ($created > 0) {
                        Notification::make()
                            ->success()
                            ->title('Permissions created')
                            ->body("Created {$created} permissions for {$resourceName}")
                            ->send();
                    }

                    if (! empty($errors)) {
                        Notification::make()
                            ->warning()
                            ->title('Some permissions failed')
                            ->body(implode(', ', $errors))
                            ->send();
                    }

                    if ($created === 0 && empty($errors)) {
                        Notification::make()
                            ->warning()
                            ->title('No permissions created')
                            ->body('All permissions already exist')
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Bulk Create Permissions')
                ->modalDescription('This will create multiple permissions for a resource at once.')
                ->modalSubmitActionLabel('Create Permissions'),

            Actions\Action::make('create_common_permissions')
                ->label('Create Common Permissions')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->action(function () {
                    $commonPermissions = [
                        // System permissions
                        ['name' => 'access_admin_panel', 'category' => 'system'],
                        ['name' => 'view_dashboard', 'category' => 'system'],
                        ['name' => 'manage_settings', 'category' => 'system'],

                        // Analytics permissions
                        ['name' => 'view_analytics', 'category' => 'analytics'],
                        ['name' => 'export_data', 'category' => 'analytics'],

                        // User management
                        ['name' => 'manage_users', 'category' => 'users'],
                        ['name' => 'assign_roles', 'category' => 'users'],
                    ];

                    $created = 0;
                    foreach ($commonPermissions as $permissionData) {
                        $permission = Permission::firstOrCreate([
                            'name' => $permissionData['name'],
                            'guard_name' => 'web',
                        ], $permissionData);

                        if ($permission->wasRecentlyCreated) {
                            $created++;
                        }
                    }

                    if ($created > 0) {
                        Notification::make()
                            ->success()
                            ->title('Common permissions created')
                            ->body("Created {$created} common system permissions")
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('No permissions created')
                            ->body('All common permissions already exist')
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Create Common Permissions')
                ->modalDescription('This will create commonly used system and management permissions.')
                ->modalSubmitActionLabel('Create Permissions'),
        ];
    }
}
