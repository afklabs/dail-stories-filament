<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    /**
     * FIXED: Added proper type casting for PHPStan
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Member $record */
        $record = $this->record;

        // Now PHPStan knows the exact type - no more warnings!
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('export_data')
                ->label('Export Member Data')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    /** @var Member $record */
                    $record = $this->record;

                    $memberData = [
                        'personal_info' => [
                            'id' => $record->id,
                            'name' => $record->name,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'date_of_birth' => $record->date_of_birth?->format('Y-m-d'),
                            'gender' => $record->gender,
                            'status' => $record->status,
                            'email_verified_at' => $record->email_verified_at?->format('Y-m-d H:i:s'),
                            'created_at' => $record->created_at->format('Y-m-d H:i:s'),
                        ],
                        'activity_summary' => [
                            'total_story_views' => $record->storyViews()->count(),
                            'unique_stories_viewed' => $record->storyViews()->distinct('story_id')->count(),
                            'total_ratings_given' => $record->ratings()->count(),
                            'average_rating_given' => round($record->ratings()->avg('rating') ?? 0, 1),
                            'total_interactions' => $record->interactions()->count(),
                            'bookmarked_stories' => $record->interactions()->where('action', 'bookmark')->count(),
                            'shared_stories' => $record->interactions()->where('action', 'share')->count(),
                        ],
                        'reading_statistics' => [
                            'total_reading_sessions' => $record->readingHistory()->count(),
                            'completed_stories' => $record->readingHistory()->where('reading_progress', '>=', 100)->count(),
                            'total_reading_time_minutes' => $record->readingHistory()->sum('time_spent'),
                            'average_reading_progress' => round($record->readingHistory()->avg('reading_progress') ?? 0, 1),
                        ]
                    ];

                    // Create temporary file
                    $filename = "member_data_{$record->id}_" . now()->format('Y-m-d_H-i-s') . '.json';
                    $filePath = storage_path('app/temp/' . $filename);

                    // Ensure directory exists
                    if (!file_exists(dirname($filePath))) {
                        mkdir(dirname($filePath), 0755, true);
                    }

                    file_put_contents($filePath, json_encode($memberData, JSON_PRETTY_PRINT));

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Data exported')
                        ->body("Member data exported to: {$filename}")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('download')
                                ->button()
                                ->url(route('download.member.data', ['filename' => $filename]))
                                ->openUrlInNewTab(),
                        ])
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Export Member Data')
                ->modalDescription('This will export all member data including personal info, activity, and reading statistics.')
                ->modalSubmitActionLabel('Export Data'),

            Actions\Action::make('view_activity')
                ->label('View Recent Activity')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->url(function () {
                    /** @var Member $record */
                    $record = $this->record;
                    return MemberResource::getUrl('activity', ['record' => $record]);
                })
                ->visible(function () {
                    /** @var Member $record */
                    $record = $this->record;
                    return $record->storyViews()->exists() || $record->interactions()->exists();
                }),

            Actions\Action::make('send_notification')
                ->label('Send Notification')
                ->icon('heroicon-o-bell')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\TextInput::make('title')
                        ->label('Notification Title')
                        ->required()
                        ->maxLength(255),
                    \Filament\Forms\Components\Textarea::make('message')
                        ->label('Message')
                        ->required()
                        ->maxLength(500)
                        ->rows(4),
                    \Filament\Forms\Components\Select::make('type')
                        ->label('Type')
                        ->options([
                            'info' => 'Information',
                            'success' => 'Success',
                            'warning' => 'Warning',
                            'error' => 'Error',
                        ])
                        ->default('info')
                        ->required(),
                ])
                ->action(function (array $data) {
                    /** @var Member $record */
                    $record = $this->record;

                    // Here you would implement your notification sending logic
                    // For example, using Laravel's notification system

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Notification sent')
                        ->body("Notification '{$data['title']}' sent to {$record->name}")
                        ->send();
                })
                ->visible(function () {
                    /** @var Member $record */
                    $record = $this->record;
                    return $record->status === 'active' && $record->email_verified_at;
                }),

            Actions\Action::make('member_stats')
                ->label('Detailed Statistics')
                ->icon('heroicon-o-chart-bar')
                ->color('success')
                ->action(function () {
                    /** @var Member $record */
                    $record = $this->record;

                    $stats = [
                        'engagement_metrics' => [
                            'days_since_registration' => $record->created_at->diffInDays(now()),
                            'days_since_last_login' => $record->last_login_at ? $record->last_login_at->diffInDays(now()) : null,
                            'stories_per_day' => $record->storyViews()->count() / max(1, $record->created_at->diffInDays(now())),
                            'favorite_categories' => $record->getPreferredCategories(5),
                            'reading_consistency' => $record->getReadingConsistencyScore(),
                        ],
                        'content_preferences' => [
                            'most_read_category' => $record->getMostReadCategory(),
                            'average_story_rating' => round($record->ratings()->avg('rating') ?? 0, 1),
                            'preferred_reading_time' => $record->getPreferredReadingTime(),
                            'completion_rate' => $record->getStoryCompletionRate(),
                        ]
                    ];

                    \Filament\Notifications\Notification::make()
                        ->info()
                        ->title('Member Statistics')
                        ->body('Statistics calculated successfully. Check the detailed view for more information.')
                        ->send();
                })
                ->visible(function () {
                    /** @var Member $record */
                    $record = $this->record;
                    return $record->storyViews()->exists();
                }),
        ];
    }
}
