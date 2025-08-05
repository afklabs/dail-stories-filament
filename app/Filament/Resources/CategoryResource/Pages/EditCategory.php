<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    /**
     * ✅ FIXED: Added proper type hint for $this->record
     */
    public function getRecord(): Category
    {
        /** @var Category $record */
        $record = parent::getRecord();
        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription(function () {
                    // ✅ FIXED: Use getRecord() method with proper type casting
                    $category = $this->getRecord();
                    $storiesCount = $category->stories()->count();

                    if ($storiesCount > 0) {
                        return "This category has {$storiesCount} stories. Deleting it will also delete all associated stories. Are you sure?";
                    }

                    return 'Are you sure you want to delete this category?';
                }),
        ];
    }

    protected function afterSave(): void
    {
        // ✅ FIXED: Use getRecord() method instead of direct $this->record access
        $category = $this->getRecord();

        // Touch all related stories to update their timestamps
        $category->stories()->touch();

        // Clear any cached data related to this category
        if (method_exists($category, 'clearCache')) {
            $category->clearCache();
        }
    }

    protected function getRedirectUrl(): string
    {
        // ✅ FIXED: Use getRecord() method with proper type casting
        $category = $this->getRecord();

        return $this->getResource()::getUrl('view', ['record' => $category->id]);
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
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // ✅ FIXED: Use getRecord() method instead of direct $this->record access
        $category = $this->getRecord();

        // Ensure slug is unique (excluding current record)
        $originalSlug = $data['slug'];
        $counter = 1;

        while (Category::where('slug', $data['slug'])
            ->where('id', '!=', $category->id)
            ->exists()
        ) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $data;
    }

    /**
     * ✅ NEW: Additional helper methods for better code organization
     */
    protected function getCategoryStoriesCount(): int
    {
        return $this->getRecord()->stories()->count();
    }

    /**
     * ✅ NEW: Method to handle category-specific operations
     */
    protected function handleCategoryUpdate(): void
    {
        $category = $this->getRecord();

        // Log the category update
        \Illuminate\Support\Facades\Log::info('Category updated', [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'stories_count' => $category->stories()->count(),
        ]);
    }

    /**
     * ✅ NEW: Override the saved method to add custom logic
     */
    protected function saved(): void
    {
        parent::saved();

        $this->handleCategoryUpdate();
    }
}
