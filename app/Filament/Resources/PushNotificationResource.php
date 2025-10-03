<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PushNotificationResource\Pages;
use App\Models\PushNotification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class PushNotificationResource extends Resource
{
    protected static ?string $model = PushNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notification Content')
                    ->description('Main content of the push notification')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter notification title')
                            ->helperText('Keep it short and attention-grabbing'),

                        Forms\Components\Textarea::make('body')
                            ->required()
                            ->rows(4)
                            ->maxLength(500)
                            ->placeholder('Enter notification message')
                            ->helperText('Maximum 500 characters')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Targeting')
                    ->description('Who should receive this notification')
                    ->schema([
                        Forms\Components\Select::make('target_type')
                            ->label('Send To')
                            ->required()
                            ->options([
                                PushNotification::TARGET_ALL => 'All Users',
                                PushNotification::TARGET_TOPIC => 'Specific Topic',
                                PushNotification::TARGET_TOKENS => 'Specific Devices',
                            ])
                            ->default(PushNotification::TARGET_ALL)
                            ->live()
                            ->helperText('Choose who will receive this notification'),

                        Forms\Components\TextInput::make('target_value')
                            ->label('Target Value')
                            ->placeholder('Enter topic name or comma-separated tokens')
                            ->helperText(fn(Forms\Get $get): string => match ($get('target_type')) {
                                PushNotification::TARGET_TOPIC => 'Enter topic name (e.g., daily_stories, all_users)',
                                PushNotification::TARGET_TOKENS => 'Enter FCM tokens separated by commas',
                                default => 'Not needed for "All Users"',
                            })
                            ->visible(
                                fn(Forms\Get $get): bool =>
                                in_array($get('target_type'), [PushNotification::TARGET_TOPIC, PushNotification::TARGET_TOKENS])
                            )
                            ->required(
                                fn(Forms\Get $get): bool =>
                                in_array($get('target_type'), [PushNotification::TARGET_TOPIC, PushNotification::TARGET_TOKENS])
                            ),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Scheduling')
                    ->description('When to send this notification')
                    ->schema([
                        Forms\Components\Toggle::make('send_now')
                            ->label('Send Immediately')
                            ->default(true)
                            ->live()
                            ->helperText('Toggle OFF to schedule for later'),

                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Schedule Date & Time')
                            ->native(false)
                            ->seconds(false)
                            ->minDate(now())
                            ->timezone('Asia/Riyadh')
                            ->displayFormat('Y-m-d H:i')
                            ->helperText('Select when to send this notification')
                            ->visible(fn(Forms\Get $get): bool => !$get('send_now'))
                            ->required(fn(Forms\Get $get): bool => !$get('send_now')),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Additional Data (Optional)')
                    ->description('Extra data for navigation or custom actions')
                    ->schema([
                        Forms\Components\KeyValue::make('data')
                            ->label('Custom Data')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->addActionLabel('Add data field')
                            ->helperText('Add custom key-value pairs (e.g., story_id: 123, action: view_story)')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->columns(1),

                Forms\Components\Section::make('Status Information')
                    ->description('Current notification status and delivery stats')
                    ->schema([
                        Forms\Components\Placeholder::make('status')
                            ->label('Current Status')
                            ->content(
                                fn(?PushNotification $record): string =>
                                $record ? ucfirst($record->status) : 'Draft'
                            ),

                        Forms\Components\Placeholder::make('created_by')
                            ->label('Created By')
                            ->content(
                                fn(?PushNotification $record): string =>
                                $record?->creator?->name ?? auth()->user()?->name ?? 'Unknown'
                            ),

                        Forms\Components\Placeholder::make('success_count')
                            ->label('Successful Deliveries')
                            ->content(
                                fn(?PushNotification $record): string =>
                                $record ? number_format($record->success_count) : '0'
                            )
                            ->visible(
                                fn(?PushNotification $record): bool =>
                                $record && $record->isSent()
                            ),

                        Forms\Components\Placeholder::make('failure_count')
                            ->label('Failed Deliveries')
                            ->content(
                                fn(?PushNotification $record): string =>
                                $record ? number_format($record->failure_count) : '0'
                            )
                            ->visible(
                                fn(?PushNotification $record): bool =>
                                $record && $record->isSent()
                            ),

                        Forms\Components\Placeholder::make('sent_at')
                            ->label('Sent At')
                            ->content(
                                fn(?PushNotification $record): string =>
                                $record?->sent_at?->format('M j, Y H:i') ?? 'Not sent yet'
                            )
                            ->visible(
                                fn(?PushNotification $record): bool =>
                                $record && $record->isSent()
                            ),
                    ])
                    ->columns(2)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => PushNotification::STATUS_DRAFT,
                        'warning' => PushNotification::STATUS_SCHEDULED,
                        'info' => PushNotification::STATUS_SENDING,
                        'success' => PushNotification::STATUS_SENT,
                        'danger' => PushNotification::STATUS_FAILED,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_type')
                    ->label('Target')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        PushNotification::TARGET_ALL => 'All Users',
                        PushNotification::TARGET_TOPIC => 'Topic',
                        PushNotification::TARGET_TOKENS => 'Devices',
                        default => $state,
                    })
                    ->colors([
                        'primary' => PushNotification::TARGET_ALL,
                        'info' => PushNotification::TARGET_TOPIC,
                        'warning' => PushNotification::TARGET_TOKENS,
                    ]),

                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Scheduled For')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('Send now')
                    ->tooltip(
                        fn(?PushNotification $record): ?string =>
                        $record?->time_until_send
                    ),

                Tables\Columns\TextColumn::make('success_count')
                    ->label('Delivered')
                    ->numeric()
                    ->sortable()
                    ->visible(fn() => request()->get('tableFilters')['status']['value'] ?? null === PushNotification::STATUS_SENT),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        PushNotification::STATUS_DRAFT => 'Draft',
                        PushNotification::STATUS_SCHEDULED => 'Scheduled',
                        PushNotification::STATUS_SENT => 'Sent',
                        PushNotification::STATUS_FAILED => 'Failed',
                    ])
                    ->multiple(),

                SelectFilter::make('target_type')
                    ->label('Target Type')
                    ->options([
                        PushNotification::TARGET_ALL => 'All Users',
                        PushNotification::TARGET_TOPIC => 'Topic',
                        PushNotification::TARGET_TOKENS => 'Devices',
                    ])
                    ->multiple(),

                Filter::make('scheduled')
                    ->label('Scheduled Only')
                    ->query(fn(Builder $query): Builder => $query->where('status', PushNotification::STATUS_SCHEDULED)),

                Filter::make('sent_today')
                    ->label('Sent Today')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->where('status', PushNotification::STATUS_SENT)
                            ->whereDate('sent_at', today())
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn(PushNotification $record): bool =>
                        $record->isDraft() || $record->isScheduled()
                    ),

                Tables\Actions\Action::make('send_now')
                    ->label('Send Now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send Notification Now?')
                    ->modalDescription(
                        fn(PushNotification $record): string =>
                        "This will send '{$record->title}' to {$record->getTargetTypeLabel()} immediately."
                    )
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

                Tables\Actions\DeleteAction::make()
                    ->visible(
                        fn(PushNotification $record): bool =>
                        !$record->isSent()
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPushNotifications::route('/'),
            'create' => Pages\CreatePushNotification::route('/create'),
            'view' => Pages\ViewPushNotification::route('/{record}'),
            'edit' => Pages\EditPushNotification::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', PushNotification::STATUS_SCHEDULED)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
