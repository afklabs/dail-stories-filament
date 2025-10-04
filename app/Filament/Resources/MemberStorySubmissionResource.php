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

    protected static ?string $navigationLabel = 'قصص الأعضاء';

    protected static ?string $modelLabel = 'قصة عضو';

    protected static ?string $pluralModelLabel = 'قصص الأعضاء';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات القصة')
                    ->schema([
                        Forms\Components\TextInput::make('story_title')
                            ->label('عنوان القصة')
                            ->required()
                            ->maxLength(255)
                            ->disabled(),

                        Forms\Components\Select::make('category_id')
                            ->label('التصنيف')
                            ->options(Category::pluck('name', 'id'))
                            ->required()
                            ->disabled(),

                        Forms\Components\RichEditor::make('story_content')
                            ->label('محتوى القصة')
                            ->required()
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('معلومات العضو')
                    ->schema([
                        Forms\Components\TextInput::make('member.name')
                            ->label('اسم العضو')
                            ->disabled(),

                        Forms\Components\TextInput::make('member.email')
                            ->label('البريد الإلكتروني')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('حالة المراجعة')
                    ->schema([
                        Forms\Components\Select::make('submission_status')
                            ->label('الحالة')
                            ->options([
                                'pending' => 'قيد الانتظار',
                                'archived' => 'مؤرشف',
                                'published' => 'منشور',
                                'rejected' => 'مرفوض',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('ملاحظات الإدارة')
                            ->rows(3)
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
                    ->label('عنوان القصة')
                    ->searchable()
                    ->limit(50)
                    ->sortable(),

                Tables\Columns\TextColumn::make('member.name')
                    ->label('العضو')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('التصنيف')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('submission_status')
                    ->label('الحالة')
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
                    ->label('تاريخ الإرسال')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('submission_status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار',
                        'archived' => 'مؤرشف',
                        'published' => 'منشور',
                        'rejected' => 'مرفوض',
                    ]),

                SelectFilter::make('category_id')
                    ->label('التصنيف')
                    ->relationship('category', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('archive')
                    ->label('أرشفة')
                    ->icon('heroicon-o-archive-box')
                    ->color('secondary')
                    ->visible(fn($record) => $record->submission_status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->markAsArchived(auth()->id())),

                Tables\Actions\Action::make('reject')
                    ->label('رفض')
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
            ->with(['member', 'category']);
    }
}
