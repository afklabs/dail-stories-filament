<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Filament\Resources\TagResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription(function () {
                    $storiesCount = $this->record->stories()->count();
                    if ($storiesCount > 0) {
                        return "This tag is used in {$storiesCount} stories. Deleting it will remove the tag from all stories. Are you sure?";
                    }

                    return 'Are you sure you want to delete this tag?';
                }),
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
            ->title('Tag updated')
            ->body('The tag has been updated successfully.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Auto-generate slug if not provided
        if (empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Ensure slug is unique (excluding current record)
        $originalSlug = $data['slug'];
        $counter = 1;

        while (\App\Models\Tag::where('slug', $data['slug'])
            ->where('id', '!=', $this->record->id)
            ->exists()) {
            $data['slug'] = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $data;
    }
}
