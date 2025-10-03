<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Resources\PushNotificationResource;
use App\Models\PushNotification;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPushNotification extends EditRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

            Actions\Action::make('send_now')
                ->label('Send Now')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Send This Notification Now?')
                ->modalDescription(
                    fn(PushNotification $record): string =>
                    "This will send '{$record->title}' immediately."
                )
                ->modalSubmitActionLabel('Yes, Send Now')
                ->action(function (PushNotification $record) {
                    try {
                        app(\App\Services\PushNotificationScheduler::class)->sendNotification($record);

                        Notification::make()
                            ->success()
                            ->title('Notification Sent!')
                            ->body("Successfully sent to {$record->success_count} devices")
                            ->send();

                        return redirect($this->getResource()::getUrl('view', ['record' => $record]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Send Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->visible(
                    fn(PushNotification $record): bool =>
                    $record->isDraft() || $record->isScheduled()
                ),

            Actions\DeleteAction::make()
                ->visible(fn(PushNotification $record): bool => !$record->isSent()),
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
            ->title('Notification Updated')
            ->body('The push notification has been updated successfully.');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Add "send_now" toggle based on scheduled_at
        $data['send_now'] = empty($data['scheduled_at']);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle "send now" toggle
        if (!empty($data['send_now'])) {
            $data['scheduled_at'] = null;
            // Keep status as is (draft or scheduled)
        } else {
            // If scheduled_at is set, ensure status is scheduled
            if (!empty($data['scheduled_at'])) {
                $data['status'] = PushNotification::STATUS_SCHEDULED;
            }
        }

        // Remove the temporary "send_now" field
        unset($data['send_now']);

        // Clean target_value if target_type is "all"
        if ($data['target_type'] === PushNotification::TARGET_ALL) {
            $data['target_value'] = null;
        }

        return $data;
    }
}
