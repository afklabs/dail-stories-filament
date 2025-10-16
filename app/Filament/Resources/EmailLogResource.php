<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EmailLogResource\Pages;
use App\Models\EmailLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationLabel = 'Email Logs';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Email Details')
                    ->schema([
                        Forms\Components\TextInput::make('recipient_email')
                            ->label('المستلم')
                            ->disabled(),

                        Forms\Components\TextInput::make('subject')
                            ->label('الموضوع')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('body')
                            ->label('المحتوى')
                            ->disabled()
                            ->rows(10)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Status Information')
                    ->schema([
                        Forms\Components\Placeholder::make('status')
                            ->label('الحالة')
                            ->content(fn(EmailLog $record): string => match ($record->status) {
                                'pending' => '⏳ قيد الانتظار',
                                'sent' => '✅ تم الإرسال',
                                'failed' => '❌ فشل',
                                'bounced' => '⚠️ مرتد',
                                default => $record->status,
                            }),

                        Forms\Components\Placeholder::make('email_type')
                            ->label('نوع البريد')
                            ->content(fn(EmailLog $record): string => match ($record->email_type) {
                                'welcome' => '👋 ترحيبي',
                                'password_reset' => '🔑 إعادة تعيين كلمة المرور',
                                'promotional' => '📢 ترويجي',
                                'notification' => '🔔 إشعار',
                                default => $record->email_type,
                            }),

                        Forms\Components\Placeholder::make('sent_at')
                            ->label('تاريخ الإرسال')
                            ->content(fn(?string $state): string => $state ? \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s') : 'لم يتم الإرسال بعد'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Tracking Information')
                    ->schema([
                        Forms\Components\Placeholder::make('opened_at')
                            ->label('تاريخ الفتح')
                            ->content(fn(?string $state): string => $state ? \Carbon\Carbon::parse($state)->diffForHumans() : 'لم يتم الفتح'),

                        Forms\Components\Placeholder::make('open_count')
                            ->label('عدد مرات الفتح')
                            ->content(fn(int $state): string => $state . ' مرة'),

                        Forms\Components\Placeholder::make('clicked_at')
                            ->label('تاريخ النقر')
                            ->content(fn(?string $state): string => $state ? \Carbon\Carbon::parse($state)->diffForHumans() : 'لم يتم النقر'),

                        Forms\Components\Placeholder::make('click_count')
                            ->label('عدد مرات النقر')
                            ->content(fn(int $state): string => $state . ' مرة'),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Error Information')
                    ->schema([
                        Forms\Components\Placeholder::make('error_message')
                            ->label('رسالة الخطأ')
                            ->content(fn(?string $state): string => $state ?? 'لا توجد أخطاء')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(EmailLog $record): bool => $record->status === 'failed'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('recipient_email')
                    ->label('المستلم')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),

                Tables\Columns\TextColumn::make('subject')
                    ->label('الموضوع')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 40 ? $state : null;
                    }),

                Tables\Columns\BadgeColumn::make('email_type')
                    ->label('النوع')
                    ->colors([
                        'success' => EmailLog::TYPE_WELCOME,
                        'warning' => EmailLog::TYPE_PASSWORD_RESET,
                        'primary' => EmailLog::TYPE_PROMOTIONAL,
                        'info' => EmailLog::TYPE_NOTIFICATION,
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'welcome' => 'ترحيبي',
                        'password_reset' => 'إعادة تعيين',
                        'promotional' => 'ترويجي',
                        'notification' => 'إشعار',
                        default => $state,
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'welcome' => 'heroicon-m-hand-raised',
                        'password_reset' => 'heroicon-m-key',
                        'promotional' => 'heroicon-m-megaphone',
                        'notification' => 'heroicon-m-bell',
                        default => 'heroicon-m-envelope',
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'secondary' => EmailLog::STATUS_PENDING,
                        'success' => EmailLog::STATUS_SENT,
                        'danger' => EmailLog::STATUS_FAILED,
                        'warning' => EmailLog::STATUS_BOUNCED,
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'قيد الانتظار',
                        'sent' => 'تم الإرسال',
                        'failed' => 'فشل',
                        'bounced' => 'مرتد',
                        default => $state,
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'pending' => 'heroicon-m-clock',
                        'sent' => 'heroicon-m-check-circle',
                        'failed' => 'heroicon-m-x-circle',
                        'bounced' => 'heroicon-m-exclamation-triangle',
                        default => 'heroicon-m-envelope',
                    }),

                Tables\Columns\IconColumn::make('opened_at')
                    ->label('مفتوح')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-o-envelope')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(
                        fn(EmailLog $record): string =>
                        $record->opened_at
                            ? 'تم الفتح: ' . $record->opened_at->diffForHumans() . ' (' . $record->open_count . ' مرة)'
                            : 'لم يتم الفتح بعد'
                    ),

                Tables\Columns\IconColumn::make('clicked_at')
                    ->label('نقر')
                    ->boolean()
                    ->trueIcon('heroicon-o-cursor-arrow-rays')
                    ->falseIcon('heroicon-o-cursor-arrow-rays')
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->tooltip(
                        fn(EmailLog $record): string =>
                        $record->clicked_at
                            ? 'تم النقر: ' . $record->clicked_at->diffForHumans() . ' (' . $record->click_count . ' مرة)'
                            : 'لم يتم النقر بعد'
                    ),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('member.name')
                    ->label('العضو')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sentByUser.name')
                    ->label('المرسل')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('email_type')
                    ->label('نوع البريد')
                    ->options([
                        'welcome' => 'ترحيبي',
                        'password_reset' => 'إعادة تعيين كلمة المرور',
                        'promotional' => 'ترويجي',
                        'notification' => 'إشعار',
                    ]),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار',
                        'sent' => 'تم الإرسال',
                        'failed' => 'فشل',
                        'bounced' => 'مرتد',
                    ]),

                Filter::make('opened')
                    ->label('تم فتحه')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('opened_at')),

                Filter::make('clicked')
                    ->label('تم النقر عليه')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('clicked_at')),

                Filter::make('failed')
                    ->label('فشل الإرسال')
                    ->query(fn(Builder $query): Builder => $query->where('status', 'failed')),

                Tables\Filters\Filter::make('sent_today')
                    ->label('تم إرساله اليوم')
                    ->query(fn(Builder $query): Builder => $query->whereDate('sent_at', today())),

                Tables\Filters\Filter::make('sent_this_week')
                    ->label('تم إرساله هذا الأسبوع')
                    ->query(fn(Builder $query): Builder => $query->whereBetween('sent_at', [now()->startOfWeek(), now()->endOfWeek()])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('resend')
                    ->label('إعادة الإرسال')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn(EmailLog $record): bool => $record->status === 'failed')
                    ->action(function (EmailLog $record) {
                        // Implement resend logic here
                        \Filament\Notifications\Notification::make()
                            ->title('جاري إعادة الإرسال')
                            ->body('سيتم إعادة إرسال البريد الإلكتروني قريباً')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailLogs::route('/'),
            'view' => Pages\ViewEmailLog::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'failed')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'failed')->count() > 0 ? 'danger' : null;
    }
}
