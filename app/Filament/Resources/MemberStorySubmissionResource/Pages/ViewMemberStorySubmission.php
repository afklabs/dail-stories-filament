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
                ->label('أرشفة')
                ->icon('heroicon-o-archive-box')
                ->color('secondary')
                ->visible(fn() => $this->record->submission_status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('أرشفة القصة')
                ->modalDescription('هل أنت متأكد من أرشفة هذه القصة؟')
                ->action(function () {
                    $this->record->markAsArchived(auth()->id());

                    Notification::make()
                        ->title('تم الأرشفة بنجاح')
                        ->success()
                        ->send();

                    return redirect()->route('filament.admin.resources.member-story-submissions.index');
                }),

            Actions\Action::make('reject')
                ->label('رفض')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => $this->record->submission_status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('رفض القصة')
                ->modalDescription('هل أنت متأكد من رفض هذه القصة؟')
                ->action(function () {
                    $this->record->markAsRejected(auth()->id());

                    Notification::make()
                        ->title('تم الرفض')
                        ->warning()
                        ->send();

                    return redirect()->route('filament.admin.resources.member-story-submissions.index');
                }),

            Actions\Action::make('create_story')
                ->label('إنشاء قصة من هذا النص')
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
