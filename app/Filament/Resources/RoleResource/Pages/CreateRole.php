<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

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
        // Ensure guard_name is set
        $data['guard_name'] = $data['guard_name'] ?? 'web';

        // Clean up the name
        $data['name'] = strtolower(trim($data['name']));
        $data['name'] = preg_replace('/[^a-z0-9_]/', '_', $data['name']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Log role creation
        activity()
            ->causedBy(auth()->user())
            ->performedOn($this->record)
            ->log('Role created: '.$this->record->name);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_preset_roles')
                ->label('Create Preset Roles')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->action(function () {
                    $presetRoles = [
                        [
                            'name' => 'content_manager',
                            'guard_name' => 'web',
                            'description' => 'Can manage stories, categories, and tags',
                        ],
                        [
                            'name' => 'member_moderator',
                            'guard_name' => 'web',
                            'description' => 'Can moderate members and their interactions',
                        ],
                        [
                            'name' => 'analytics_viewer',
                            'guard_name' => 'web',
                            'description' => 'Can view analytics and reports',
                        ],
                        [
                            'name' => 'editor',
                            'guard_name' => 'web',
                            'description' => 'Can edit content but not publish',
                        ],
                    ];

                    $created = 0;
                    foreach ($presetRoles as $roleData) {
                        if (! \Spatie\Permission\Models\Role::where('name', $roleData['name'])->exists()) {
                            \Spatie\Permission\Models\Role::create($roleData);
                            $created++;
                        }
                    }

                    if ($created > 0) {
                        Notification::make()
                            ->success()
                            ->title('Preset roles created')
                            ->body("Created {$created} preset roles successfully.")
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('No roles created')
                            ->body('All preset roles already exist.')
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Create Preset Roles')
                ->modalDescription('This will create common roles: Content Manager, Member Moderator, Analytics Viewer, and Editor.')
                ->modalSubmitActionLabel('Create Roles'),
        ];
    }
}
