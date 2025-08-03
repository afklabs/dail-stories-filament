<?php
// app/Filament/Resources/UserResource/Pages/ListUsers.php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create User')
                ->icon('heroicon-o-plus'),

            Actions\Action::make('export_users')
                ->label('Export Users')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    // Export functionality
                    return response()->streamDownload(function () {
                        $users = User::with(['roles'])->get();
                        $csv = fopen('php://output', 'w');

                        // Headers
                        fputcsv($csv, [
                            'ID',
                            'Name',
                            'Email',
                            'Email Verified',
                            'Roles',
                            'Created At',
                            'Updated At'
                        ]);

                        // Data
                        foreach ($users as $user) {
                            fputcsv($csv, [
                                $user->id,
                                $user->name,
                                $user->email,
                                $user->email_verified_at ? 'Yes' : 'No',
                                $user->roles->pluck('name')->join(', '),
                                $user->created_at->format('Y-m-d H:i:s'),
                                $user->updated_at->format('Y-m-d H:i:s'),
                            ]);
                        }

                        fclose($csv);
                    }, 'users-export-' . now()->format('Y-m-d-H-i-s') . '.csv');
                })
                ->requiresConfirmation()
                ->modalHeading('Export Users')
                ->modalDescription('Download a CSV file containing all user data?'),

            Actions\ActionGroup::make([
                Actions\Action::make('verify_selected')
                    ->label('Verify Selected Users')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->action(function () {
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Bulk Actions')
                            ->body('Bulk verification feature coming soon.')
                            ->send();
                    }),

                Actions\Action::make('suspend_selected')
                    ->label('Suspend Selected Users')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->action(function () {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Bulk Actions')
                            ->body('Bulk suspension feature coming soon.')
                            ->send();
                    }),
            ])
                ->label('Bulk Actions')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Users')
                ->badge(User::count()),

            'verified' => Tab::make('Verified')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotNull('email_verified_at'))
                ->badge(User::whereNotNull('email_verified_at')->count())
                ->badgeColor('success'),

            'unverified' => Tab::make('Unverified')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('email_verified_at'))
                ->badge(User::whereNull('email_verified_at')->count())
                ->badgeColor('warning'),

            'recent' => Tab::make('Recent')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('created_at', '>=', now()->subDays(7)))
                ->badge(User::where('created_at', '>=', now()->subDays(7))->count())
                ->badgeColor('info'),

            'admins' => Tab::make('Admins')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('roles', fn($q) => $q->where('name', 'admin')))
                ->badge(User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->count())
                ->badgeColor('primary'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Add user statistics widgets here
        ];
    }
}
