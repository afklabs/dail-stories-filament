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
                    try {
                        return Gate::allows('view_analytics') ||
                            (auth()->check() && auth()->user()?->hasPermissionTo('view_analytics'));
                    } catch (\Exception $e) {
                        return auth()->check() && (auth()->user()?->is_admin ?? false);
                    }
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('copy_link')
                    ->label('Copy Link')
                    ->icon('heroicon-o-link')
                    ->action(function () {
                        /** @var Story $record */
                        $record = $this->record;

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Link ready')
                            ->body('Story link: /stories/' . ($record->slug ?? $record->id))
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
                                Infolists\Components\TextEntry::make('active_from')
                                    ->label('Active From')
                                    ->dateTime()
                                    ->placeholder('Not scheduled'),

                                Infolists\Components\TextEntry::make('reading_time_minutes')
                                    ->formatStateUsing(fn($state) => ($state ?? 0) . ' minutes')
                                    ->icon('heroicon-o-clock'),

                                Infolists\Components\TextEntry::make('active')
                                    ->badge()
                                    ->color(fn(?bool $state): string => $state ? 'success' : 'danger')
                                    ->formatStateUsing(fn(?bool $state): string => $state ? 'Active' : 'Inactive'),
                            ]),
                    ]),


                // Original Submission Section (if story came from member submission)
                Infolists\Components\Section::make('Original Submission')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('originalSubmission.story_title')
                                    ->label('Original Title')
                                    ->weight(FontWeight::Bold)
                                    ->getStateUsing(fn($record) => $record->originalSubmission?->story_title)
                                    ->url(fn($record) => $record->originalSubmission
                                        ? route('filament.admin.resources.member-story-submissions.view', [
                                            'record' => $record->originalSubmission->id
                                        ])
                                        : null)
                                    ->openUrlInNewTab()
                                    ->icon('heroicon-o-document-text')
                                    ->color('info')
                                    ->placeholder('N/A'),

                                Infolists\Components\TextEntry::make('originalSubmission.member.name')
                                    ->label('Submitted By')
                                    ->getStateUsing(fn($record) => $record->originalSubmission?->member?->name)
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-o-user')
                                    ->url(fn($record) => $record->originalSubmission
                                        ? route('filament.admin.resources.members.view', [
                                            'record' => $record->originalSubmission->member_id
                                        ])
                                        : null)
                                    ->openUrlInNewTab()
                                    ->placeholder('N/A'),

                                Infolists\Components\TextEntry::make('originalSubmission.submitted_at')
                                    ->label('Submitted Date')
                                    ->getStateUsing(fn($record) => $record->originalSubmission?->submitted_at)
                                    ->dateTime('Y-m-d H:i:s')
                                    ->badge()
                                    ->color('warning')
                                    ->icon('heroicon-o-calendar')
                                    ->placeholder('N/A'),
                            ]),

                        Infolists\Components\Grid::make(1)
                            ->schema([
                                Infolists\Components\TextEntry::make('originalSubmission.admin_notes')
                                    ->label('Review Notes')
                                    ->getStateUsing(fn($record) => $record->originalSubmission?->admin_notes)
                                    ->prose()
                                    ->placeholder('No admin notes')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->visible(fn($record) => $record->originalSubmission !== null)
                    ->icon('heroicon-o-user-circle')
                    ->description('This story was created from a member submission'),

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

    /**
     * Get the original submission for this story
     */
    protected function getOriginalSubmission($record): ?\App\Models\MemberStorySubmission
    {
        if (!$record) {
            return null;
        }

        // Cache the result to avoid multiple database queries
        return once(function () use ($record) {
            return \App\Models\MemberStorySubmission::with(['member'])
                ->where('published_story_id', $record->id)
                ->first();
        });
    }
}
