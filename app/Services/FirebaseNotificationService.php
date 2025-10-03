<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct()
    {
        $credentialsPath = storage_path('app/firebase/firebase-credentials.json');

        if (!file_exists($credentialsPath)) {
            Log::error('Firebase credentials file not found');
            return;
        }

        $factory = (new Factory)->withServiceAccount($credentialsPath);
        $this->messaging = $factory->createMessaging();
    }

    /**
     * Send notification to specific tokens
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        if (empty($tokens)) {
            return ['success' => 0, 'failure' => 0];
        }

        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);

            $report = $this->messaging->sendMulticast($message, $tokens);

            Log::info('Firebase notification sent', [
                'success' => $report->successes()->count(),
                'failure' => $report->failures()->count(),
            ]);

            return [
                'success' => $report->successes()->count(),
                'failure' => $report->failures()->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Firebase send failed: ' . $e->getMessage());
            return ['success' => 0, 'failure' => count($tokens)];
        }
    }

    /**
     * Send notification to topic
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);

            Log::info('Firebase topic notification sent', ['topic' => $topic]);
            return true;
        } catch (\Exception $e) {
            Log::error('Firebase topic send failed: ' . $e->getMessage());
            return false;
        }
    }
}
