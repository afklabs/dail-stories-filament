<?php

namespace App\Filament\Resources\MemberStorySubmissionResource\Pages;

use App\Filament\Resources\MemberStorySubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditMemberStorySubmission extends EditRecord
{
    protected static string $resource = MemberStorySubmissionResource::class;

    /**
     * ✅ FIX: Load member relationship before filling form
     * This ensures member.name and member.email are populated
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load the member relationship if not already loaded
        if (!$this->record->relationLoaded('member')) {
            $this->record->load('member');
        }

        return $data;
    }

    /**
     * ✅ Optional: Auto-fill reviewed_at and reviewed_by when status changes
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If status changed from pending to something else, record review info
        if (
            $this->record->submission_status === 'pending' &&
            isset($data['submission_status']) &&
            $data['submission_status'] !== 'pending'
        ) {
            $data['reviewed_at'] = now();
            $data['reviewed_by'] = auth()->id();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->submission_status !== 'published'),
        ];
    }

    /**
     * ✅ Custom success notification
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Submission Updated')
            ->body('The member story submission has been updated successfully.');
    }

    /**
     * ✅ Redirect back to index after save
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
