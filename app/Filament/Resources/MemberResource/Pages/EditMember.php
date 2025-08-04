<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    /**
     * FIXED: Added proper type casting for PHPStan
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Member $record */
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
                ->modalHeading('Delete Member')
                ->modalDescription(function () {
                    /** @var Member $record */
                    $record = $this->record;

                    $storiesCount = $record->storyViews()->count();
                    $ratingsCount = $record->ratings()->count();

                    if ($storiesCount > 0 || $ratingsCount > 0) {
                        return "This member has {$storiesCount} story views and {$ratingsCount} ratings. Deleting will remove all their activity data. Are you sure?";
                    }

                    return 'Are you sure you want to delete this member?';
                })
                ->modalSubmitActionLabel('Yes, delete member'),

            Actions\Action::make('toggle_status')
                ->label(function () {
                    /** @var Member $record */
                    $record = $this->record;
                    return $record->status === 'active' ? 'Deactivate' : 'Activate';
                })
                ->icon(function () {
                    /** @var Member $record */
                    $record = $this->record;
                    return $record->status === 'active' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle';
                })
                ->color(function () {
                    /** @var Member $record */
                    $record = $this->record;
                    return $record->status === 'active' ? 'warning' : 'success';
                })
                ->action(function () {
                    /** @var Member $record */
                    $record = $this->record;

                    $newStatus = $record->status === 'active' ? 'inactive' : 'active';
                    $record->update(['status' => $newStatus]);

                    Notification::make()
                        ->success()
                        ->title('Member status updated')
                        ->body("Member has been {$newStatus}")
                        ->send();

                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation()
                ->modalHeading(function () {
                    /** @var Member $record */
                    $record = $this->record;
                    return $record->status === 'active' ? 'Deactivate Member' : 'Activate Member';
                })
                ->modalDescription(function () {
                    /** @var Member $record */
                    $record = $this->record;
                    return $record->status === 'active'
                        ? 'This will prevent the member from accessing their account.'
                        : 'This will restore the member\'s access to their account.';
                }),

            Actions\Action::make('reset_password')
                ->label('Reset Password')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\TextInput::make('new_password')
                        ->label('New Password')
                        ->password()
                        ->required()
                        ->minLength(8)
                        ->confirmed(),
                    \Filament\Forms\Components\TextInput::make('new_password_confirmation')
                        ->label('Confirm New Password')
                        ->password()
                        ->required(),
                ])
                ->action(function (array $data) {
                    /** @var Member $record */
                    $record = $this->record;

                    $record->update([
                        'password' => Hash::make($data['new_password'])
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Password reset')
                        ->body('Member password has been updated successfully')
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Reset Member Password')
                ->modalDescription('This will set a new password for this member.')
                ->modalSubmitActionLabel('Reset Password'),
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
            ->title('Member updated')
            ->body('The member has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Member $record */
        $record = $this->record;

        // Remove empty password field
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            // Hash the password if provided
            $data['password'] = Hash::make($data['password']);
        }

        // Handle avatar upload path
        if (isset($data['avatar']) && $data['avatar']) {
            // The file upload component will handle the path
            // Just ensure we're storing the relative path
            if (is_string($data['avatar']) && !str_starts_with($data['avatar'], 'members/avatars/')) {
                $data['avatar'] = 'members/avatars/' . $data['avatar'];
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Member $record */
        $record = $this->getRecord();

        // Clear member-related caches
        $record->clearCache();

        // Log member update
        $auth = app('auth');
        $currentUser = $auth->user();

        if ($currentUser) {
            \Illuminate\Support\Facades\Log::info('Member updated', [
                'member_id' => $record->id,
                'member_email' => $record->email,
                'updated_by' => $currentUser->id,
                'updated_at' => now(),
            ]);
        }

        // Update last modified timestamp for related stories if member was active in them
        if ($record->wasChanged(['status', 'email_verified_at'])) {
            // Touch stories the member has interacted with recently
            $record->storyViews()
                ->where('viewed_at', '>=', now()->subDays(30))
                ->with('story')
                ->get()
                ->pluck('story')
                ->unique('id')
                ->each(fn($story) => $story?->touch());
        }
    }
}
