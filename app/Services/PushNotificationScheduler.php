<?php

namespace App\Services;

use App\Models\PushNotification;
use Illuminate\Support\Facades\Log;

class PushNotificationScheduler
{
    protected FirebaseNotificationService $firebaseService;

    public function __construct(FirebaseNotificationService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send a push notification immediately
     *
     * @param PushNotification $notification
     * @return array
     * @throws \Exception
     */
    public function sendNotification(PushNotification $notification): array
    {
        try {
            // Mark as sending
            $notification->markAsSending();
            $notification->update(['sent_by' => auth()->id()]);

            Log::info('Sending push notification', [
                'notification_id' => $notification->id,
                'title' => $notification->title,
                'target_type' => $notification->target_type,
            ]);

            $result = match ($notification->target_type) {
                PushNotification::TARGET_ALL => $this->sendToAllUsers($notification),
                PushNotification::TARGET_TOPIC => $this->sendToTopic($notification),
                PushNotification::TARGET_TOKENS => $this->sendToTokens($notification),
                default => throw new \Exception("Invalid target type: {$notification->target_type}"),
            };

            // Mark as sent with statistics
            $notification->markAsSent($result['success'], $result['failure']);

            Log::info('Push notification sent successfully', [
                'notification_id' => $notification->id,
                'success_count' => $result['success'],
                'failure_count' => $result['failure'],
            ]);

            return $result;
        } catch (\Exception $e) {
            // Mark as failed
            $notification->markAsFailed($e->getMessage());

            Log::error('Push notification failed', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Send to all users (via 'all_users' topic)
     *
     * @param PushNotification $notification
     * @return array
     */
    protected function sendToAllUsers(PushNotification $notification): array
    {
        $success = $this->firebaseService->sendToTopic(
            'all_users',
            $notification->title,
            $notification->body,
            $notification->data ?? []
        );

        // Firebase sendToTopic returns bool, not counts
        // We estimate success (actual count not available without tokens)
        return [
            'success' => $success ? 1 : 0,
            'failure' => $success ? 0 : 1,
        ];
    }

    /**
     * Send to specific topic
     *
     * @param PushNotification $notification
     * @return array
     */
    protected function sendToTopic(PushNotification $notification): array
    {
        if (empty($notification->target_value)) {
            throw new \Exception('Topic name is required for topic targeting');
        }

        $success = $this->firebaseService->sendToTopic(
            $notification->target_value,
            $notification->title,
            $notification->body,
            $notification->data ?? []
        );

        return [
            'success' => $success ? 1 : 0,
            'failure' => $success ? 0 : 1,
        ];
    }

    /**
     * Send to specific device tokens
     *
     * @param PushNotification $notification
     * @return array
     */
    protected function sendToTokens(PushNotification $notification): array
    {
        if (empty($notification->target_value)) {
            throw new \Exception('Device tokens are required for token targeting');
        }

        // Parse comma-separated tokens
        $tokens = array_map('trim', explode(',', $notification->target_value));
        $tokens = array_filter($tokens); // Remove empty values

        if (empty($tokens)) {
            throw new \Exception('No valid device tokens provided');
        }

        $result = $this->firebaseService->sendToTokens(
            $tokens,
            $notification->title,
            $notification->body,
            $notification->data ?? []
        );

        return [
            'success' => $result['success'] ?? 0,
            'failure' => $result['failure'] ?? 0,
        ];
    }

    /**
     * Process all scheduled notifications that are ready to send
     *
     * @return array
     */
    public function processScheduledNotifications(): array
    {
        $notifications = PushNotification::readyToSend()->get();

        $processed = [
            'total' => $notifications->count(),
            'sent' => 0,
            'failed' => 0,
        ];

        foreach ($notifications as $notification) {
            try {
                $this->sendNotification($notification);
                $processed['sent']++;
            } catch (\Exception $e) {
                $processed['failed']++;
                Log::error('Failed to process scheduled notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Scheduled notifications processed', $processed);

        return $processed;
    }

    /**
     * Get upcoming scheduled notifications
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUpcomingNotifications(int $limit = 10)
    {
        return PushNotification::where('status', PushNotification::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get notification statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'total' => PushNotification::count(),
            'draft' => PushNotification::where('status', PushNotification::STATUS_DRAFT)->count(),
            'scheduled' => PushNotification::where('status', PushNotification::STATUS_SCHEDULED)->count(),
            'sent' => PushNotification::where('status', PushNotification::STATUS_SENT)->count(),
            'failed' => PushNotification::where('status', PushNotification::STATUS_FAILED)->count(),
            'sent_today' => PushNotification::where('status', PushNotification::STATUS_SENT)
                ->whereDate('sent_at', today())
                ->count(),
            'total_delivered' => PushNotification::where('status', PushNotification::STATUS_SENT)
                ->sum('success_count'),
            'total_failed_deliveries' => PushNotification::where('status', PushNotification::STATUS_SENT)
                ->sum('failure_count'),
        ];
    }
}
