<?php
// app/Filament/Resources/StoryResource/Pages/ViewStory.php

namespace App\Filament\Resources\StoryResource\Pages;

use App\Filament\Resources\StoryResource;
use App\Models\Story;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Gate;

class ViewStory extends ViewRecord
{
    protected static string $resource = StoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit Story')
                ->icon('heroicon-o-pencil'),

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Story')
                ->modalDescription('Are you sure you want to delete this story? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete story'),

            Actions\Action::make('publish')
                ->label(fn() => $this->record->status === 'published' ? 'Unpublish' : 'Publish')
                ->icon(fn() => $this->record->status === 'published' ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                ->color(fn() => $this->record->status === 'published' ? 'warning' : 'success')
                ->action(function () {
                    $newStatus = $this->record->status === 'published' ? 'draft' : 'published';
                    $this->record->update(['status' => $newStatus]);

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Story ' . ($newStatus === 'published' ? 'published' : 'unpublished'))
                        ->body('The story status has been updated successfully.')
                        ->send();

                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation(fn() => $this->record->status === 'published')
                ->modalHeading(fn() => $this->record->status === 'published' ? 'Unpublish Story' : 'Publish Story')
                ->modalDescription(fn() => $this->record->status === 'published'
                    ? 'Are you sure you want to unpublish this story?'
                    : 'Are you sure you want to publish this story?'),

            Actions\Action::make('duplicate')
                ->label('Duplicate Story')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->action(function () {
                    $newStory = $this->record->replicate();
                    $newStory->title = $this->record->title . ' (Copy)';
                    $newStory->slug = $this->record->slug . '-copy';
                    $newStory->status = 'draft';
                    $newStory->views = 0;
                    $newStory->published_at = null;
                    $newStory->save();

                    // Copy relationships if needed
                    if ($this->record->tags) {
                        $newStory->tags()->sync($this->record->tags->pluck('id'));
                    }

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Story duplicated')
                        ->body('A copy of this story has been created as a draft.')
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->label('View Copy')
                                ->url(StoryResource::getUrl('edit', ['record' => $newStory]))
                        ])
                        ->send();
                }),

            Actions\Action::make('view_analytics')
                ->label('View Analytics')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->action(function () {
                    \Filament\Notifications\Notification::make()
                        ->info()
                        ->title('Analytics')
                        ->body('Story analytics feature coming soon.')
                        ->send();
                })
                ->visible(function () {
                    // Use Gate facade for permission checking - always recognized by static analysis
                    try {
                        return \Illuminate\Support\Facades\Gate::allows('view_analytics') ||
                            (auth()->check() && auth()->user()->hasPermissionTo('view_analytics'));
                    } catch (\Exception $e) {
                        // Fallback: allow if user is admin
                        return auth()->check() && (auth()->user()->is_admin ?? false);
                    }
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('copy_link')
                    ->label('Copy Link')
                    ->icon('heroicon-o-link')
                    ->action(function () {
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Link ready')
                            ->body('Story link: /stories/' . $this->record->slug)
                            ->send();
                    }),

                Actions\Action::make('social_share')
                    ->label('Social Media')
                    ->icon('heroicon-o-megaphone')
                    ->action(function () {
                        \Filament\Notifications\Notification::make()
                            ->info()
                            ->title('Share')
                            ->body('Social sharing feature coming soon.')
                            ->send();
                    }),
            ])
                ->label('Share')
                ->icon('heroicon-o-share')
                ->color('gray'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Story Overview')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('title')
                                        ->weight(FontWeight::Bold)
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                                    Infolists\Components\TextEntry::make('status')
                                        ->badge()
                                        ->color(fn(?string $state): string => match ($state) {
                                            'published' => 'success',
                                            'scheduled' => 'warning',
                                            'expired' => 'danger',
                                            'draft' => 'gray',
                                            default => 'secondary',
                                        }),

                                    Infolists\Components\TextEntry::make('category.name')
                                        ->label('Category')
                                        ->badge()
                                        ->color('primary')
                                        ->placeholder('No category'),

                                    Infolists\Components\TextEntry::make('views')
                                        ->label('Total Views')
                                        ->numeric()
                                        ->icon('heroicon-o-eye')
                                        ->placeholder('0'),
                                ]),

                            Infolists\Components\ImageEntry::make('image')
                                ->disk('public')
                                ->height(200)
                                ->width(300)
                                ->placeholder('/images/placeholder-story.png'),
                        ])->from('lg'),
                    ]),

                Infolists\Components\Section::make('Content Preview')
                    ->schema([
                        Infolists\Components\TextEntry::make('excerpt')
                            ->columnSpanFull()
                            ->prose()
                            ->placeholder('No excerpt available'),

                        Infolists\Components\TextEntry::make('content')
                            ->html()
                            ->columnSpanFull()
                            ->prose()
                            ->placeholder('No content available'),
                    ]),

                Infolists\Components\Section::make('Publishing Details')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('published_at')
                                    ->dateTime()
                                    ->placeholder('Not published'),

                                Infolists\Components\TextEntry::make('reading_time_minutes')
                                    ->formatStateUsing(fn($state) => ($state ?? 0) . ' minutes')
                                    ->icon('heroicon-o-clock'),

                                Infolists\Components\TextEntry::make('active')
                                    ->badge()
                                    ->color(fn(?bool $state): string => $state ? 'success' : 'danger')
                                    ->formatStateUsing(fn(?bool $state): string => $state ? 'Active' : 'Inactive'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Tags')
                    ->schema([
                        Infolists\Components\TextEntry::make('tags.name')
                            ->badge()
                            ->separator(', ')
                            ->color('info')
                            ->placeholder('No tags'),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime(),
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
