<?php

namespace App\Filament\Resources\StoryResource\Pages;

use App\Filament\Resources\StoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditStory extends EditRecord
{
    protected static string $resource = StoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
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
            ->title('Story updated')
            ->body('The story has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

        return $data;
    }

    protected function afterSave(): void
    {
        // Log the update in publishing history
        $story = $this->record;
        $originalData = $this->record->getOriginal();
        
        $changedFields = [];
        
        // Check what publishing-related fields changed
        if ($originalData['active'] !== $story->active) {
            $changedFields[] = 'active';
        }
        if ($originalData['active_from'] !== $story->active_from?->toDateTimeString()) {
            $changedFields[] = 'active_from';
        }
        if ($originalData['active_until'] !== $story->active_until?->toDateTimeString()) {
            $changedFields[] = 'active_until';
        }

        // Only log if publishing-related fields changed
        if (!empty($changedFields)) {
            $action = 'updated';
            
            // Determine specific action
            if (!$originalData['active'] && $story->active) {
                $action = 'published';
            } elseif ($originalData['active'] && !$story->active) {
                $action = 'unpublished';
            } elseif ($originalData['active'] && $story->active) {
                $action = 'republished';
            }

            \App\Models\StoryPublishingHistory::create([
                'story_id' => $story->id,
                'user_id' => auth()->id(),
                'action' => $action,
                'previous_active_status' => $originalData['active'],
                'new_active_status' => $story->active,
                'previous_active_from' => $originalData['active_from'] ? new \Carbon\Carbon($originalData['active_from']) : null,
                'previous_active_until' => $originalData['active_until'] ? new \Carbon\Carbon($originalData['active_until']) : null,
                'new_active_from' => $story->active_from,
                'new_active_until' => $story->active_until,
                'changed_fields' => $changedFields,
                'notes' => 'Story publishing settings updated',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
    }
}