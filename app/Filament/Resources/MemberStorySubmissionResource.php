<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberStorySubmissionResource\Pages;
use App\Models\MemberStorySubmission;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class MemberStorySubmissionResource extends Resource
{
    protected static ?string $model = MemberStorySubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Members Stories';

    protected static ?string $modelLabel = 'Member Story';

    protected static ?string $pluralModelLabel = 'Members Stories';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Story Info')
                    ->schema([
                        Forms\Components\TextInput::make('story_title')
                            ->label('Story Title')
                            ->required()
                            ->maxLength(255)
                            ->disabled(),

                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->options(Category::pluck('name', 'id'))
                            ->required()
                            ->disabled(),

                        Forms\Components\RichEditor::make('story_content')
                            ->label('Story Content')
                            ->required()
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // ✅ FIXED: Member Info Section with Placeholder (Read-Only Display)
                Forms\Components\Section::make('Member Info')
                    ->schema([
                        Forms\Components\Placeholder::make('member_name')
                            ->label('Member Name')
                            ->content(fn($record) => $record?->member?->name ?? 'N/A'),

                        Forms\Components\Placeholder::make('member_email')
                            ->label('Member Email')
                            ->content(fn($record) => $record?->member?->email ?? 'N/A'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Review Status')
                    ->schema([
                        Forms\Components\Select::make('submission_status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'archived' => 'Archived',
                                'published' => 'Published',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(3)
                            ->placeholder('Add review notes or feedback...')
                            ->helperText('Internal notes visible only to admins')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('story_title')
                    ->label('Story Title')
                    ->searchable()
                    ->limit(50)
                    ->sortable()
                    ->tooltip(fn($record) => $record->story_title),

                Tables\Columns\TextColumn::make('member.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\BadgeColumn::make('submission_status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'secondary' => 'archived',
                        'success' => 'published',
                        'danger' => 'rejected',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'pending',
                        'heroicon-o-archive-box' => 'archived',
                        'heroicon-o-check-circle' => 'published',
                        'heroicon-o-x-circle' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submission Date')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('submission_status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'archived' => 'Archived',
                        'published' => 'Published',
                        'rejected' => 'Rejected',
                    ]),

                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('secondary')
                    ->visible(fn($record) => $record->submission_status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->markAsArchived(auth()->id())),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->submission_status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->markAsRejected(auth()->id())),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('submitted_at', 'desc');
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
            'index' => Pages\ListMemberStorySubmissions::route('/'),
            'view' => Pages\ViewMemberStorySubmission::route('/{record}'),
            'edit' => Pages\EditMemberStorySubmission::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['member', 'category']); // ✅ Always load relationships
    }
}
