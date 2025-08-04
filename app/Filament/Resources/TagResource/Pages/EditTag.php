<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Filament\Resources\TagResource;
use App\Models\Tag;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    /**
     * FIXED: Added proper type casting for PHPStan
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Tag $record */
        $record = $this->record;

        // Now PHPStan knows the exact type - no more warnings!
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Tag')
                ->modalDescription(function () {
                    /** @var Tag $record */
                    $record = $this->record;

                    $storiesCount = $record->stories()->count();
                    if ($storiesCount > 0) {
                        return "This tag is used in {$storiesCount} stories. Deleting it will remove the tag from all stories. Are you sure?";
                    }

                    return 'Are you sure you want to delete this tag?';
                })
                ->modalSubmitActionLabel('Yes, delete tag'),

            Actions\Action::make('merge_tags')
                ->label('Merge with Another Tag')
                ->icon('heroicon-o-arrows-pointing-in')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('target_tag_id')
                        ->label('Merge into Tag')
                        ->options(function () {
                            /** @var Tag $record */
                            $record = $this->record;

                            return Tag::where('id', '!=', $record->id)
                                ->orderBy('name')
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->required()
                        ->helperText('All stories with this tag will be moved to the selected tag'),
                ])
                ->action(function (array $data) {
                    /** @var Tag $record */
                    $record = $this->record;

                    $targetTag = Tag::find($data['target_tag_id']);
                    if (!$targetTag) {
                        return;
                    }

                    // Get all stories with the current tag
                    $stories = $record->stories()->get();

                    // Attach them to the target tag (will avoid duplicates)
                    foreach ($stories as $story) {
                        $story->tags()->syncWithoutDetaching([$targetTag->id]);
                        $story->tags()->detach($record->id);
                    }

                    $storiesCount = $stories->count();

                    Notification::make()
                        ->success()
                        ->title('Tags merged successfully')
                        ->body("{$storiesCount} stories moved from '{$record->name}' to '{$targetTag->name}'")
                        ->send();

                    // Delete the current tag after merging
                    $record->delete();

                    // Redirect to target tag
                    return redirect(TagResource::getUrl('view', ['record' => $targetTag]));
                })
                ->requiresConfirmation()
                ->modalHeading('Merge Tags')
                ->modalDescription('This will move all stories from this tag to another tag and delete this tag.')
                ->modalSubmitActionLabel('Merge Tags')
                ->visible(function () {
                    /** @var Tag $record */
                    $record = $this->record;
                    return $record->stories()->exists() && Tag::where('id', '!=', $record->id)->exists();
                }),

            Actions\Action::make('duplicate')
                ->label('Duplicate Tag')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\TextInput::make('new_name')
                        ->label('New Tag Name')
                        ->required()
                        ->default(function () {
                            /** @var Tag $record */
                            $record = $this->record;
                            return $record->name . ' Copy';
                        })
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                            $set('new_slug', Str::slug($state));
                        }),
                    \Filament\Forms\Components\TextInput::make('new_slug')
                        ->label('New Tag Slug')
                        ->required()
                        ->unique('tags', 'slug')
                        ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']),
                ])
                ->action(function (array $data) {
                    /** @var Tag $record */
                    $record = $this->record;

                    $newTag = Tag::create([
                        'name' => $data['new_name'],
                        'slug' => $data['new_slug'],
                    ]);

                    // Optionally copy all story associations
                    $storyIds = $record->stories()->pluck('stories.id');
                    $newTag->stories()->sync($storyIds);

                    Notification::make()
                        ->success()
                        ->title('Tag duplicated')
                        ->body("Created '{$newTag->name}' with all story associations")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->button()
                                ->url(TagResource::getUrl('view', ['record' => $newTag])),
                        ])
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Duplicate Tag')
                ->modalDescription('This will create a copy of this tag with all its story associations.')
                ->modalSubmitActionLabel('Duplicate'),

            Actions\Action::make('bulk_update_stories')
                ->label('Bulk Update Stories')
                ->icon('heroicon-o-pencil-square')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\Select::make('action')
                        ->label('Action')
                        ->options([
                            'add_tag' => 'Add another tag to all stories',
                            'remove_tag' => 'Remove another tag from all stories',
                            'set_featured' => 'Mark all stories as featured',
                            'unset_featured' => 'Unmark all stories as featured',
                        ])
                        ->required(),
                    \Filament\Forms\Components\Select::make('target_tag_id')
                        ->label('Target Tag')
                        ->options(function () {
                            /** @var Tag $record */
                            $record = $this->record;

                            return Tag::where('id', '!=', $record->id)
                                ->orderBy('name')
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->visible(fn(\Filament\Forms\Get $get) => in_array($get('action'), ['add_tag', 'remove_tag'])),
                ])
                ->action(function (array $data) {
                    /** @var Tag $record */
                    $record = $this->record;

                    $stories = $record->stories()->get();
                    $count = 0;

                    foreach ($stories as $story) {
                        switch ($data['action']) {
                            case 'add_tag':
                                if ($data['target_tag_id']) {
                                    $story->tags()->syncWithoutDetaching([$data['target_tag_id']]);
                                    $count++;
                                }
                                break;
                            case 'remove_tag':
                                if ($data['target_tag_id']) {
                                    $story->tags()->detach($data['target_tag_id']);
                                    $count++;
                                }
                                break;
                            case 'set_featured':
                                $story->update(['is_featured' => true]);
                                $count++;
                                break;
                            case 'unset_featured':
                                $story->update(['is_featured' => false]);
                                $count++;
                                break;
                        }
                    }

                    Notification::make()
                        ->success()
                        ->title('Bulk update completed')
                        ->body("Updated {$count} stories")
                        ->send();
                })
                ->visible(function () {
                    /** @var Tag $record */
                    $record = $this->record;
                    return $record->stories()->exists();
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
        /** @var Tag $record */
        $record = $this->record;

        // Auto-generate slug if not provided
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Ensure slug is unique (excluding current record)
        $originalSlug = $data['slug'];
        $counter = 1;

        while (Tag::where('slug', $data['slug'])
            ->where('id', '!=', $record->id)
            ->exists()
        ) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Tag $record */
        $record = $this->getRecord();

        // Clear tag-related caches
        Cache::forget("tag.{$record->id}.stories");
        Cache::forget("tag_list");
        Cache::forget("popular_tags");

        // Touch related stories to update their timestamps
        $record->stories()->touch();

        // Log tag update
        $auth = app('auth');
        $currentUser = $auth->user();

        if ($currentUser) {
            \Illuminate\Support\Facades\Log::info('Tag updated', [
                'tag_id' => $record->id,
                'tag_name' => $record->name,
                'tag_slug' => $record->slug,
                'stories_count' => $record->stories()->count(),
                'updated_by' => $currentUser->id,
                'updated_at' => now(),
            ]);
        }
    }
}
