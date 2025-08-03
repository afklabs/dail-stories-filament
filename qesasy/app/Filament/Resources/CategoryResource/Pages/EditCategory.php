<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription(function () {
                    $storiesCount = $this->record->stories()->count();
                    if ($storiesCount > 0) {
                        return "This category has {$storiesCount} stories. Deleting it will also delete all associated stories. Are you sure?";
                    }

                    return 'Are you sure you want to delete this category?';
                }),
        ];
    }

protected function afterSave(): void
{
    $record = $this->getRecord();
    
    if ($record instanceof \App\Models\Category) {
        $record->stories()->touch();
    }
}

protected function getRedirectUrl(): string
{
    $record = $this->getRecord();
    
    if ($record instanceof \App\Models\Category) {
        return $this->getResource()::getUrl('view', ['record' => $record->id]);
    }
    
    return $this->getResource()::getUrl('index');
}

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Category updated')
            ->body('The category has been updated successfully.');
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

        while (\App\Models\Category::where('slug', $data['slug'])
            ->where('id', '!=', $this->record->id)
            ->exists()) {
            $data['slug'] = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $data;
    }
}
