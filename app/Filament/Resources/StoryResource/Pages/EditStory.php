<?php

namespace App\Filament\Resources\StoryResource\Pages;

use App\Filament\Resources\StoryResource;
use App\Models\Story;
use App\Models\StoryPublishingHistory;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

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
        if (empty($data['excerpt']) && ! empty($data['content'])) {
            $plainText = strip_tags($data['content']);
            $plainText = preg_replace('/\s+/', ' ', $plainText);
            $data['excerpt'] = substr(trim($plainText), 0, 160) . '...';
        }

        // Auto-calculate reading time
        if (! empty($data['content'])) {
            $wordCount = str_word_count(strip_tags($data['content']));
            $data['reading_time_minutes'] = max(1, ceil($wordCount / 200));
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // ✅ FIXED: Add type assertion for PHPStan
        /** @var Story $story */
        $story = $this->record;

        // ✅ FIXED: Add type validation
        if (!$story instanceof Story) {
            return;
        }

        $originalData = $story->getOriginal();

        $changedFields = [];

        // ✅ FIXED: Safe property access with null coalescing
        $originalActive = $originalData['active'] ?? false;
        $originalActiveFrom = $originalData['active_from'] ?? null;
        $originalActiveUntil = $originalData['active_until'] ?? null;

        // Check what publishing-related fields changed
        if ($originalActive !== $story->active) {
            $changedFields[] = 'active';
        }
        if ($originalActiveFrom !== $story->active_from?->toDateTimeString()) {
            $changedFields[] = 'active_from';
        }
        if ($originalActiveUntil !== $story->active_until?->toDateTimeString()) {
            $changedFields[] = 'active_until';
        }

        // Only log if publishing-related fields changed
        if (! empty($changedFields)) {
            $action = 'updated';

            // Determine specific action
            if (! $originalActive && $story->active) {
                $action = 'published';
            } elseif ($originalActive && ! $story->active) {
                $action = 'unpublished';
            } elseif ($originalActive && $story->active) {
                $action = 'republished';
            }

            StoryPublishingHistory::create([
                'story_id' => $story->id,
                'user_id' => auth()->id(),
                'action' => $action,
                'previous_active_status' => $originalActive,
                'new_active_status' => $story->active,
                'previous_active_from' => $originalActiveFrom ? new Carbon($originalActiveFrom) : null,
                'previous_active_until' => $originalActiveUntil ? new Carbon($originalActiveUntil) : null,
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
