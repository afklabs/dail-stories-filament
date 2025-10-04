<?php

namespace App\Filament\Resources\MemberStorySubmissionResource\Pages;

use App\Filament\Resources\MemberStorySubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Filament\Forms;

class ViewMemberStorySubmission extends ViewRecord
{
    protected static string $resource = MemberStorySubmissionResource::class;

    /**
     * ✅ Override infolist to display all submission data
     * This is the BEST PRACTICE in Filament v3 for view pages
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Story Information Section
                Infolists\Components\Section::make('Story Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('story_title')
                                    ->label('Story Title')
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->columnSpanFull(),

                                Infolists\Components\TextEntry::make('category.name')
                                    ->label('Category')
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('submitted_at')
                                    ->label('Submitted At')
                                    ->dateTime('Y-m-d H:i:s')
                                    ->badge()
                                    ->color('success'),
                            ]),

                        Infolists\Components\TextEntry::make('story_content')
                            ->label('Story Content')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                // Member Information Section
                Infolists\Components\Section::make('Member Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('member.name')
                                    ->label('Member Name')
                                    ->weight(FontWeight::SemiBold)
                                    ->icon('heroicon-o-user')
                                    ->copyable()
                                    ->tooltip('Click to copy'),

                                Infolists\Components\TextEntry::make('member.email')
                                    ->label('Email Address')
                                    ->icon('heroicon-o-envelope')
                                    ->copyable()
                                    ->tooltip('Click to copy'),

                                Infolists\Components\TextEntry::make('member.status')
                                    ->label('Member Status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'active' => 'success',
                                        'inactive' => 'warning',
                                        'suspended' => 'danger',
                                        'banned' => 'danger',
                                        default => 'secondary',
                                    }),

                                Infolists\Components\TextEntry::make('member.created_at')
                                    ->label('Member Since')
                                    ->date('Y-m-d')
                                    ->icon('heroicon-o-calendar'),
                            ]),
                    ])
                    ->collapsible(),

                // Review Status Section
                Infolists\Components\Section::make('Review Status')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('submission_status')
                                    ->label('Submission Status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'archived' => 'secondary',
                                        'published' => 'success',
                                        'rejected' => 'danger',
                                        default => 'secondary',
                                    })
                                    ->icon(fn(string $state): string => match ($state) {
                                        'pending' => 'heroicon-o-clock',
                                        'archived' => 'heroicon-o-archive-box',
                                        'published' => 'heroicon-o-check-circle',
                                        'rejected' => 'heroicon-o-x-circle',
                                        default => 'heroicon-o-question-mark-circle',
                                    }),

                                Infolists\Components\TextEntry::make('reviewed_at')
                                    ->label('Reviewed At')
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('Not reviewed yet')
                                    ->icon('heroicon-o-clock'),

                                Infolists\Components\TextEntry::make('reviewer.name')
                                    ->label('Reviewed By')
                                    ->placeholder('Not reviewed yet')
                                    ->icon('heroicon-o-user'),
                            ]),
                    ])
                    ->collapsible(),

                // Metadata Section
                Infolists\Components\Section::make('Metadata')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('id')
                                    ->label('Submission ID')
                                    ->badge()
                                    ->color('gray'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('Y-m-d H:i:s'),

                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('Y-m-d H:i:s')
                                    ->since(),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(true),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            // ✅ NEW: Quick Edit Admin Notes Action (Editable in View Page)
            Actions\Action::make('edit_admin_notes')
                ->label('Edit Admin Notes')
                ->icon('heroicon-o-pencil-square')
                ->color('info')
                ->form([
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Admin Notes')
                        ->rows(5)
                        ->placeholder('Add your review notes here...')
                        ->helperText('Quick edit admin notes without leaving the view page'),
                ])
                ->fillForm([
                    'admin_notes' => $this->record->admin_notes,
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'admin_notes' => $data['admin_notes'],
                    ]);

                    Notification::make()
                        ->title('Admin Notes Updated')
                        ->success()
                        ->send();

                    // Refresh the page to show updated notes
                    $this->refreshFormData([
                        'admin_notes' => $data['admin_notes'],
                    ]);
                }),

            Actions\Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('secondary')
                ->visible(fn() => $this->record->submission_status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Archive Story')
                ->modalDescription('Are you sure you want to archive this story submission?')
                ->modalSubmitActionLabel('Yes, Archive It')
                ->action(function () {
                    $this->record->markAsArchived(auth()->id());

                    Notification::make()
                        ->title('Story Archived Successfully')
                        ->success()
                        ->send();

                    return redirect()->route('filament.admin.resources.member-story-submissions.index');
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => $this->record->submission_status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Reject Story')
                ->modalDescription('Are you sure you want to reject this story submission? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, Reject It')
                ->action(function () {
                    $this->record->markAsRejected(auth()->id());

                    Notification::make()
                        ->title('Story Rejected')
                        ->warning()
                        ->send();

                    return redirect()->route('filament.admin.resources.member-story-submissions.index');
                }),

            Actions\Action::make('create_story')
                ->label('Create Story from This')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->visible(fn() => $this->record->submission_status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Create Story')
                ->modalDescription('This will create a new story from this submission. The submission will remain unchanged.')
                ->url(fn() => route('filament.admin.resources.stories.create', [
                    'title' => $this->record->story_title,
                    'content' => $this->record->story_content,
                    'category_id' => $this->record->category_id,
                ])),

            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->submission_status !== 'published'),
        ];
    }
}
