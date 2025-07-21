<?php

namespace App\Filament\Resources\StoryResource\Pages;

use App\Filament\Resources\StoryResource;
use App\Models\StoryPublishingHistory;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;


class CreateStory extends CreateRecord
{
    protected static string $resource = StoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Story created')
            ->body('The story has been created successfully.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate excerpt if not provided
        if (empty($data['excerpt']) && !empty($data['content'])) {
            $plainText = strip_tags($data['content']);
            $plainText = preg_replace('/\s+/', ' ', $plainText);
            $data['excerpt'] = substr(trim($plainText), 0, 160) . '...';
        }

        // Auto-calculate reading time
        if (!empty($data['content'])) {
            $wordCount = str_word_count(strip_tags($data['content']));
            $data['reading_time_minutes'] = max(1, ceil($wordCount / 200));
        }

        // Set default active_from if publishing and not set
        if ($data['active'] && empty($data['active_from'])) {
            $data['active_from'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Log the creation in publishing history
        $story = $this->record;
        
        if ($story->active) {
            StoryPublishingHistory::create([
                'story_id' => $story->id,
                'user_id' => auth()->id(),
                'action' => 'published',
                'previous_active_status' => false,
                'new_active_status' => true,
                'previous_active_from' => null,
                'previous_active_until' => null,
                'new_active_from' => $story->active_from,
                'new_active_until' => $story->active_until,
                'notes' => 'Story created and published',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
    }
}