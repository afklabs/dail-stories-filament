<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;

class ViewPermission extends ViewRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
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
                }),

            Actions\Action::make('check_usage')
                ->label('Check Usage')
                ->icon('heroicon-o-magnifying-glass')
                ->color('info')
                ->action(function () {
                    $roles = $this->record->roles;
                    $users = $this->record->users;
                    $totalUsers = collect();

                    // Get users through roles
                    foreach ($roles as $role) {
                        $totalUsers = $totalUsers->merge($role->users);
                    }

                    // Add users with direct permission
                    $totalUsers = $totalUsers->merge($users)->unique('id');

                    $message = "Permission Usage Report:\n\n";
                    $message .= "Assigned to {$roles->count()} role(s):\n";
                    foreach ($roles as $role) {
                        $message .= "- {$role->name} ({$role->users->count()} users)\n";
                    }

                    $message .= "\nDirect user assignments: {$users->count()}\n";
                    $message .= "Total affected users: {$totalUsers->count()}\n";

                    \Filament\Notifications\Notification::make()
                        ->info()
                        ->title('Permission Usage')
                        ->body($message)
                        ->persistent()
                        ->send();
                }),

            Actions\Action::make('assign_to_all_roles')
                ->label('Assign to All Roles')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->action(function () {
                    $roles = \Spatie\Permission\Models\Role::all();
                    $assigned = 0;

                    foreach ($roles as $role) {
                        if (! $role->hasPermissionTo($this->record)) {
                            $role->givePermissionTo($this->record);
                            $assigned++;
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Permission assigned to all roles')
                        ->body("Added to {$assigned} roles (already existed in others)")
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Assign to All Roles')
                ->modalDescription('This will assign this permission to every role in the system.')
                ->modalSubmitActionLabel('Assign to All'),

            Actions\Action::make('export_usage')
                ->label('Export Usage Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    $roles = $this->record->roles;
                    $users = $this->record->users;

                    $content = "Permission Usage Report\n";
                    $content .= "======================\n\n";
                    $content .= "Permission: {$this->record->name}\n";
                    $content .= "Guard: {$this->record->guard_name}\n";
                    $content .= 'Category: '.($this->record->category ?? 'None')."\n";
                    $content .= 'Description: '.($this->record->description ?? 'None')."\n\n";

                    $content .= "Assigned Roles ({$roles->count()}):\n";
                    $content .= "--------------------\n";
                    foreach ($roles as $role) {
                        $content .= "- {$role->name} ({$role->users->count()} users)\n";
                    }

                    $content .= "\nDirect User Assignments ({$users->count()}):\n";
                    $content .= "--------------------------------\n";
                    foreach ($users as $user) {
                        $content .= "- {$user->name} ({$user->email})\n";
                    }

                    $content .= "\nGenerated: ".now()->format('Y-m-d H:i:s')."\n";

                    return response()->streamDownload(function () use ($content) {
                        echo $content;
                    }, "permission_{$this->record->name}_usage.txt");
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Permission Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Permission Name')
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('guard_name')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('category')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'stories' => 'success',
                                        'members' => 'info',
                                        'categories' => 'warning',
                                        'users' => 'primary',
                                        'system' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Other'),

                                Infolists\Components\TextEntry::make('usage_summary')
                                    ->label('Usage Summary')
                                    ->formatStateUsing(function () {
                                        $rolesCount = $this->record->roles()->count();
                                        $usersCount = $this->record->users()->count();

                                        return "{$rolesCount} roles, {$usersCount} direct users";
                                    })
                                    ->badge()
                                    ->color('info'),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ]),

                Infolists\Components\Section::make('Assigned Roles')
                    ->description('Roles that have this permission')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('roles')
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->weight(FontWeight::Bold)
                                            ->badge()
                                            ->color('success'),
                                        Infolists\Components\TextEntry::make('users_count')
                                            ->label('Users')
                                            ->formatStateUsing(fn ($record) => $record->users()->count())
                                            ->badge()
                                            ->color('info'),
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Created')
                                            ->date()
                                            ->color('gray'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Direct User Assignments')
                    ->description('Users who have this permission directly (not through roles)')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('users')
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->weight(FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('email')
                                            ->color('gray'),
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Joined')
                                            ->date()
                                            ->color('gray'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('All Affected Users')
                    ->description('All users who have this permission (through roles or direct assignment)')
                    ->schema([
                        Infolists\Components\TextEntry::make('all_users_summary')
                            ->label('Summary')
                            ->formatStateUsing(function () {
                                $totalUsers = collect();

                                // Users through roles
                                foreach ($this->record->roles as $role) {
                                    $totalUsers = $totalUsers->merge($role->users);
                                }

                                // Direct users
                                $totalUsers = $totalUsers->merge($this->record->users);

                                return $totalUsers->unique('id')->count().' unique users have this permission';
                            })
                            ->badge()
                            ->color('warning'),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('System Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime(),
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
