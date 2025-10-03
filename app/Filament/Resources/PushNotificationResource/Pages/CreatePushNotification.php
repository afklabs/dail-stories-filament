<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Resources\PushNotificationResource;
use App\Models\PushNotification;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePushNotification extends CreateRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Notification Created')
            ->body('The push notification has been created successfully.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set creator
        $data['created_by'] = auth()->id();

        // Set IP and user agent for audit
        $data['ip_address'] = request()->ip();
        $data['user_agent'] = request()->userAgent();

        // Handle "send now" toggle
        if (!empty($data['send_now'])) {
            $data['scheduled_at'] = null;
            $data['status'] = PushNotification::STATUS_DRAFT; // Will be sent after creation
        } else {
            // If scheduled, set status to scheduled
            if (!empty($data['scheduled_at'])) {
                $data['status'] = PushNotification::STATUS_SCHEDULED;
            } else {
                $data['status'] = PushNotification::STATUS_DRAFT;
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

    protected function afterCreate(): void
    {
        /** @var PushNotification $notification */
        $notification = $this->record;

        // If it was marked to "send now", trigger immediate sending
        if ($notification->isDraft() && $notification->scheduled_at === null) {
            try {
                app(\App\Services\PushNotificationScheduler::class)->sendNotification($notification);

                Notification::make()
                    ->success()
                    ->title('Notification Sent!')
                    ->body("Successfully delivered to {$notification->success_count} devices")
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->danger()
                    ->title('Send Failed')
                    ->body($e->getMessage())
                    ->send();
            }
        } elseif ($notification->isScheduled()) {
            Notification::make()
                ->info()
                ->title('Notification Scheduled')
                ->body("Will be sent at {$notification->scheduled_at->format('M j, Y H:i')}")
                ->send();
        }
    }
}
