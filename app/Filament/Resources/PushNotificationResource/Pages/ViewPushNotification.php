<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Resources\PushNotificationResource;
use App\Models\PushNotification;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;

class ViewPushNotification extends ViewRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(
                    fn(PushNotification $record): bool =>
                    $record->isDraft() || $record->isScheduled()
                ),

            Actions\Action::make('resend')
                ->label('Resend Notification')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Resend This Notification?')
                ->modalDescription('This will create a copy of this notification and send it immediately.')
                ->modalSubmitActionLabel('Yes, Resend')
                ->action(function (PushNotification $record) {
                    try {
                        // Create a copy
                        $newNotification = $record->replicate();
                        $newNotification->status = PushNotification::STATUS_DRAFT;
                        $newNotification->scheduled_at = null;
                        $newNotification->sent_at = null;
                        $newNotification->success_count = 0;
                        $newNotification->failure_count = 0;
                        $newNotification->created_by = auth()->id();
                        $newNotification->sent_by = null;
                        $newNotification->error_message = null;
                        $newNotification->save();

                        // Send immediately
                        app(\App\Services\PushNotificationScheduler::class)->sendNotification($newNotification);

                        Notification::make()
                            ->success()
                            ->title('Notification Resent!')
                            ->body("Successfully sent to {$newNotification->success_count} devices")
                            ->send();

                        return redirect($this->getResource()::getUrl('view', ['record' => $newNotification]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Resend Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->visible(fn(PushNotification $record): bool => $record->isSent()),

            Actions\Action::make('send_now')
                ->label('Send Now')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Send This Notification Now?')
                ->modalDescription('This will send the notification immediately.')
                ->modalSubmitActionLabel('Yes, Send Now')
                ->action(function (PushNotification $record) {
                    try {
                        app(\App\Services\PushNotificationScheduler::class)->sendNotification($record);

                        Notification::make()
                            ->success()
                            ->title('Notification Sent!')
                            ->body("Successfully sent to {$record->success_count} devices")
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Send Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->visible(
                    fn(PushNotification $record): bool =>
                    $record->isDraft() || $record->isScheduled()
                ),

            Actions\DeleteAction::make()
                ->visible(fn(PushNotification $record): bool => !$record->isSent()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Notification Content')
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->weight(FontWeight::Bold)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('body')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Infolists\Components\Section::make('Targeting & Status')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                                    ->color(fn(string $state): string => match ($state) {
                                        PushNotification::STATUS_DRAFT => 'gray',
                                        PushNotification::STATUS_SCHEDULED => 'warning',
                                        PushNotification::STATUS_SENDING => 'info',
                                        PushNotification::STATUS_SENT => 'success',
                                        PushNotification::STATUS_FAILED => 'danger',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('target_type')
                                    ->label('Send To')
                                    ->formatStateUsing(
                                        fn(PushNotification $record): string =>
                                        $record->getTargetTypeLabel()
                                    ),

                                Infolists\Components\TextEntry::make('scheduled_at')
                                    ->label('Scheduled For')
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder('Send immediately')
                                    ->tooltip(
                                        fn(?PushNotification $record): ?string =>
                                        $record?->time_until_send
                                    ),
                            ]),
                    ]),

                Infolists\Components\Section::make('Delivery Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_recipients')
                                    ->label('Total Recipients')
                                    ->getStateUsing(
                                        fn(PushNotification $record): int =>
                                        $record->success_count + $record->failure_count
                                    )
                                    ->numeric(),

                                Infolists\Components\TextEntry::make('success_count')
                                    ->label('Successful')
                                    ->numeric()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('failure_count')
                                    ->label('Failed')
                                    ->numeric()
                                    ->color('danger'),

                                Infolists\Components\TextEntry::make('success_rate')
                                    ->label('Success Rate')
                                    ->suffix('%')
                                    ->color(fn(float $state): string => $state >= 95 ? 'success' : ($state >= 80 ? 'warning' : 'danger')),
                            ]),
                    ])
                    ->visible(fn(PushNotification $record): bool => $record->isSent()),

                Infolists\Components\Section::make('Additional Data')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('data')
                            ->label('Custom Data Payload')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(PushNotification $record): bool => !empty($record->data))
                    ->collapsed(),

                Infolists\Components\Section::make('Error Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Error Details')
                            ->color('danger')
                            ->columnSpanFull(),
                    ])
                    ->visible(
                        fn(PushNotification $record): bool =>
                        $record->isFailed() && !empty($record->error_message)
                    ),

                Infolists\Components\Section::make('Audit Trail')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('creator.name')
                                    ->label('Created By'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('M j, Y H:i'),

                                Infolists\Components\TextEntry::make('sent_at')
                                    ->label('Sent At')
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder('Not sent yet'),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('ip_address')
                                    ->label('IP Address')
                                    ->placeholder('Not recorded'),

                                Infolists\Components\TextEntry::make('sender.name')
                                    ->label('Sent By')
                                    ->placeholder('Not sent yet')
                                    ->visible(fn(PushNotification $record): bool => $record->isSent()),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }
}
