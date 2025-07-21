<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;

class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Role')
                ->modalDescription(function () {
                    $usersCount = $this->record->users()->count();
                    if ($usersCount > 0) {
                        return "This role is assigned to {$usersCount} user(s). Deleting it will remove their permissions. Are you sure?";
                    }

                    return 'Are you sure you want to delete this role?';
                }),

            Actions\Action::make('assign_to_user')
                ->label('Assign to User')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Select::make('user_id')
                        ->label('Select User')
                        ->options(\App\Models\User::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $user = \App\Models\User::find($data['user_id']);
                    $user->assignRole($this->record);

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Role assigned')
                        ->body("Role '{$this->record->name}' has been assigned to {$user->name}")
                        ->send();
                }),

            Actions\Action::make('export_permissions')
                ->label('Export Permissions')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    $permissions = $this->record->permissions->pluck('name')->toArray();
                    $content = "Role: {$this->record->name}\n";
                    $content .= "Guard: {$this->record->guard_name}\n";
                    $content .= "Permissions:\n".implode("\n", $permissions);

                    return response()->streamDownload(function () use ($content) {
                        echo $content;
                    }, "role_{$this->record->name}_permissions.txt");
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Role Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Role Name')
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('guard_name')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('users_count')
                                    ->label('Users with this role')
                                    ->formatStateUsing(fn () => $this->record->users()->count())
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('permissions_count')
                                    ->label('Total permissions')
                                    ->formatStateUsing(fn () => $this->record->permissions()->count())
                                    ->badge()
                                    ->color('info'),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ]),

                Infolists\Components\Section::make('Permissions')
                    ->description('All permissions assigned to this role')
                    ->schema([
                        Infolists\Components\Grid::make(1)
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('permissions')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->badge()
                                            ->color(fn ($state) => $this->getPermissionColor($state)),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Users with this Role')
                    ->description('All users who have been assigned this role')
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

    private function getPermissionColor(string $permission): string
    {
        if (str_contains($permission, 'view')) {
            return 'info';
        } elseif (str_contains($permission, 'create')) {
            return 'success';
        } elseif (str_contains($permission, 'update') || str_contains($permission, 'edit')) {
            return 'warning';
        } elseif (str_contains($permission, 'delete')) {
            return 'danger';
        } else {
            return 'gray';
        }
    }
}
