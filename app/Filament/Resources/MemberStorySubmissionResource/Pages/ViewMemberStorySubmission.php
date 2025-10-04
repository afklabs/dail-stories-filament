<?php

namespace App\Filament\Resources\MemberStorySubmissionResource\Pages;

use App\Filament\Resources\MemberStorySubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewMemberStorySubmission extends ViewRecord
{
    protected static string $resource = MemberStorySubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('secondary')
                ->visible(fn() => $this->record->submission_status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Archive Story')
                ->modalDescription('Are you sure you want to archive the story?')
                ->action(function () {
                    $this->record->markAsArchived(auth()->id());

                    Notification::make()
                        ->title('Story archived successfully')
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
                ->modalDescription('Are you sure you want to reject the story?')
                ->action(function () {
                    $this->record->markAsRejected(auth()->id());

                    Notification::make()
                        ->title('Story Rejected!')
                        ->warning()
                        ->send();

                    return redirect()->route('filament.admin.resources.member-story-submissions.index');
                }),

            Actions\Action::make('create_story')
                ->label('Create a story from this content')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->visible(fn() => $this->record->submission_status === 'pending')
                ->url(fn() => route('filament.admin.resources.stories.create', [
                    'title' => $this->record->story_title,
                    'content' => $this->record->story_content,
                    'category_id' => $this->record->category_id,
                ])),

            Actions\DeleteAction::make(),
        ];
    }
}
