<?php

namespace App\Observers;

use App\Models\Story;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Facades\Log;

class StoryObserver
{
    protected $firebase;

    public function __construct(FirebaseNotificationService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Handle the Story "created" event.
     */
    public function created(Story $story): void
    {
        // Send notification when story is created and active
        if ($story->active && $story->published_at) {
            $this->sendNewStoryNotification($story);
        }
    }

    /**
     * Handle the Story "updated" event.
     */
    public function updated(Story $story): void
    {
        // Send notification when story becomes active
        if ($story->active && $story->wasChanged('active')) {
            if ($story->getOriginal('active') == false) {
                $this->sendNewStoryNotification($story);
            }
        }
    }

    /**
     * Send new story notification
     */
    protected function sendNewStoryNotification(Story $story): void
    {
        try {
            $title = 'قصة جديدة! 📖';
            $body = $story->title;
            $data = [
                'type' => 'new_story',
                'story_id' => (string) $story->id,
                'story_slug' => $story->slug,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ];

            // Send to topic (recommended for broadcasts)
            $this->firebase->sendToTopic('all_users', $title, $body, $data);

            Log::info('New story notification sent', [
                'story_id' => $story->id,
                'story_title' => $story->title,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send story notification', [
                'story_id' => $story->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
