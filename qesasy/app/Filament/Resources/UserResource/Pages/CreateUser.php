<?php
// app/Filament/Resources/UserResource/Pages/CreateUser.php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('User created')
            ->body('The user has been created successfully.')
            ->duration(5000);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Hash the password
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Set email verification if needed
        if (!isset($data['email_verified_at']) && config('auth.verification', false)) {
            $data['email_verified_at'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Log user creation (only if Spatie Activity Log is installed)
        if (class_exists('\Spatie\Activitylog\Models\Activity')) {
            try {
                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($this->record)
                    ->log('User created: ' . $this->record->name);
            } catch (\Exception $e) {
                // Silently fail if activity logging fails
                \Illuminate\Support\Facades\Log::info('Failed to log user creation activity: ' . $e->getMessage());
            }
        }

        // Send welcome email if needed
        // $this->record->sendWelcomeNotification();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_users')
                ->label('Import Users')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->action(function () {
                    // Import functionality placeholder
                    \Filament\Notifications\Notification::make()
                        ->info()
                        ->title('Import Feature')
                        ->body('User import functionality coming soon.')
                        ->send();
                })
                ->visible(function () {
                    try {
                        return auth()->check() && auth()->user()->hasPermissionTo('import_users');
                    } catch (\Exception $e) {
                        // Fallback: check if user is admin
                        return auth()->check() && (auth()->user()->is_admin ?? false);
                    }
                }),
        ];
    }
}
