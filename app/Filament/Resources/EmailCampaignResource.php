<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EmailCampaignResource\Pages;
use App\Jobs\SendCampaignEmailJob;
use App\Models\EmailCampaign;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EmailCampaignResource extends Resource
{
    protected static ?string $model = EmailCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Email Campaigns';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Campaign Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Campaign Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Summer Sale Campaign'),

                        Forms\Components\TextInput::make('subject')
                            ->label('Email Subject')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Special Offer - Limited Time!'),

                        Forms\Components\RichEditor::make('body')
                            ->label('Email Content')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'blockquote',
                            ]),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Recipients')
                    ->schema([
                        Forms\Components\Select::make('recipient_filters.type')
                            ->label('Recipient Type')
                            ->options([
                                'all' => 'All Members',
                                'active' => 'Active Members Only',
                                'specific' => 'Specific Members',
                            ])
                            ->default('all')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state === 'all' || $state === 'active') {
                                    $set('recipient_filters.emails', null);
                                }
                            }),

                        Forms\Components\Textarea::make('recipient_filters.emails')
                            ->label('Member Emails')
                            ->placeholder('Enter one email per line')
                            ->rows(5)
                            ->visible(fn(Forms\Get $get) => $get('recipient_filters.type') === 'specific')
                            ->helperText('Enter one email address per line'),
                    ]),

                Forms\Components\Section::make('Preview')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('Email Preview')
                            ->content(function (Forms\Get $get) {
                                $subject = $get('subject') ?: 'No subject';
                                $body = $get('body') ?: 'No content';

                                return new \Illuminate\Support\HtmlString('
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <div class="mb-4">
                                            <h3 class="text-lg font-bold text-gray-900">' . e($subject) . '</h3>
                                        </div>
                                        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                                            <div class="prose prose-sm max-w-none">' . $body . '</div>
                                        </div>
                                        <div class="mt-4 text-sm text-gray-500">
                                            <p>ℹ️ This is an approximate preview. Actual appearance may vary slightly in email clients.</p>
                                        </div>
                                    </div>
                                ');
                            }),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Campaign Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'secondary' => EmailCampaign::STATUS_DRAFT,
                        'warning' => EmailCampaign::STATUS_SCHEDULED,
                        'primary' => EmailCampaign::STATUS_SENDING,
                        'success' => EmailCampaign::STATUS_SENT,
                        'danger' => EmailCampaign::STATUS_FAILED,
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('total_recipients')
                    ->label('Total Recipients')
                    ->numeric(),

                Tables\Columns\TextColumn::make('sent_count')
                    ->label('Sent')
                    ->numeric()
                    ->color('success'),

                Tables\Columns\TextColumn::make('failed_count')
                    ->label('Failed')
                    ->numeric()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('delivery_rate')
                    ->label('Delivery Rate')
                    ->suffix('%')
                    ->numeric(decimalPlaces: 1),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('send')
                    ->label('Send Now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Campaign Send')
                    ->modalDescription(fn(EmailCampaign $record) => "Are you sure you want to send this campaign? It will be sent to the selected recipients.")
                    ->visible(fn(EmailCampaign $record) => $record->status === EmailCampaign::STATUS_DRAFT)
                    ->action(function (EmailCampaign $record) {
                        self::sendCampaign($record);

                        Notification::make()
                            ->success()
                            ->title('Campaign sending started')
                            ->body('Emails are being sent to members')
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn(EmailCampaign $record) => $record->status === EmailCampaign::STATUS_DRAFT),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn(EmailCampaign $record) => $record->status === EmailCampaign::STATUS_DRAFT),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailCampaigns::route('/'),
            'create' => Pages\CreateEmailCampaign::route('/create'),
            'view' => Pages\ViewEmailCampaign::route('/{record}'),
            'edit' => Pages\EditEmailCampaign::route('/{record}/edit'),
        ];
    }

    /**
     * Send campaign to recipients
     */
    protected static function sendCampaign(EmailCampaign $campaign): void
    {
        // Get recipients based on filters
        $filters = $campaign->recipient_filters ?? ['type' => 'all'];
        $recipients = self::getRecipients($filters);

        // Update campaign
        $campaign->update([
            'status' => EmailCampaign::STATUS_SENDING,
            'started_at' => now(),
            'total_recipients' => $recipients->count(),
            'sent_count' => 0,
            'failed_count' => 0,
        ]);

        // Dispatch jobs for each recipient
        foreach ($recipients as $member) {
            SendCampaignEmailJob::dispatch(
                $campaign->id,
                $member->id,
                Auth::id()
            );
        }

        // Mark as sent if all jobs dispatched
        $campaign->update([
            'status' => EmailCampaign::STATUS_SENT,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get recipients based on filters
     */
    protected static function getRecipients(array $filters)
    {
        $query = Member::query();

        switch ($filters['type'] ?? 'all') {
            case 'active':
                $query->where('status', 'active')
                    ->whereNotNull('last_login_at')
                    ->where('last_login_at', '>=', now()->subDays(30));
                break;

            case 'specific':
                if (!empty($filters['emails'])) {
                    $emails = array_filter(
                        array_map('trim', explode("\n", $filters['emails']))
                    );
                    $query->whereIn('email', $emails);
                }
                break;

            case 'all':
            default:
                // No additional filtering
                break;
        }

        return $query->get();
    }
}
