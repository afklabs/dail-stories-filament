<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoryResource\Pages;
use App\Models\Story;
use Filament\Forms;
use Filament\Forms\Form;
// ADD THIS LINE
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StoryResource extends Resource
{
    protected static ?string $model = Story::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Story Content')
                    ->description('Main content and details of the story')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $context, $state, Forms\Set $set) {
                                if ($context === 'create') {
                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                }
                            }),

                        Forms\Components\RichEditor::make('content')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ])
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                // Auto-generate excerpt if not manually set
                                if (empty($get('excerpt')) && ! empty($state)) {
                                    $plainText = strip_tags($state);
                                    $plainText = preg_replace('/\s+/', ' ', $plainText);
                                    $excerpt = substr(trim($plainText), 0, 160).'...';
                                    $set('excerpt', $excerpt);
                                }

                                // Auto-calculate reading time
                                if (! empty($state)) {
                                    $wordCount = str_word_count(strip_tags($state));
                                    $readingTime = max(1, ceil($wordCount / 200));
                                    $set('reading_time_minutes', $readingTime);
                                }
                            }),

                        Forms\Components\Textarea::make('excerpt')
                            ->rows(3)
                            ->maxLength(300)
                            ->hint('Leave empty to auto-generate from content')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Media & Classification')
                    ->description('Image upload and content categorization')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->image()
                            ->disk('public')
                            ->directory('stories')
                            ->visibility('public')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('16:9')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('675')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),

                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                    ->rows(3),
                            ]),

                        Forms\Components\Select::make('tags')
                            ->label('Tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255),
                            ]),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Publishing Settings')
                    ->description('Publication status and scheduling')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Published')
                            ->default(false)
                            ->live(),

                        Forms\Components\DateTimePicker::make('active_from')
                            ->label('Publish From')
                            ->hint('When this story should become active')
                            ->visible(fn (Forms\Get $get): bool => $get('active'))
                            ->default(now()),

                        Forms\Components\DateTimePicker::make('active_until')
                            ->label('Publish Until')
                            ->hint('When this story should expire (optional)')
                            ->visible(fn (Forms\Get $get): bool => $get('active'))
                            ->after('active_from'),

                        Forms\Components\TextInput::make('reading_time_minutes')
                            ->label('Reading Time (minutes)')
                            ->numeric()
                            ->default(5)
                            ->minValue(1)
                            ->maxValue(120)
                            ->suffix('minutes')
                            ->hint('Auto-calculated based on content'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Analytics')
                    ->description('Story performance metrics')
                    ->schema([
                        Forms\Components\TextInput::make('views')
                            ->label('Total Views')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(1)
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->disk('public')
                    ->height(60)
                    ->width(100)
                    ->defaultImageUrl(url('/images/placeholder-story.png')),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tags.name')
                    ->badge()
                    ->color('gray')
                    ->separator(',')
                    ->limit(20),

                Tables\Columns\IconColumn::make('active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        'expired' => 'danger',
                        'draft' => 'gray',
                    }),

                Tables\Columns\TextColumn::make('views')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->icon('heroicon-o-eye'),

                Tables\Columns\TextColumn::make('reading_time_minutes')
                    ->label('Reading Time')
                    ->formatStateUsing(fn ($state) => $state.' min')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('average_rating')
                    ->label('Rating')
                    ->formatStateUsing(function ($record) {
                        $rating = $record->average_rating;
                        $count = $record->total_ratings;

                        return $rating > 0 ? number_format($rating, 1).' ★ ('.$count.')' : 'No ratings';
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('active_from')
                    ->label('Publish Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('active')
                    ->options([
                        '1' => 'Published',
                        '0' => 'Draft',
                    ]),

                Filter::make('scheduled')
                    ->query(fn (Builder $query): Builder => $query->where('active', true)
                        ->where('active_from', '>', now())
                    ),

                Filter::make('expired')
                    ->query(fn (Builder $query): Builder => $query->where('active', true)
                        ->where('active_until', '<', now())
                    ),

                Filter::make('high_views')
                    ->label('High Views (>1000)')
                    ->query(fn (Builder $query): Builder => $query->where('views', '>', 1000)
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->active ? 'Unpublish' : 'Publish')
                    ->icon(fn ($record) => $record->active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn ($record) => $record->active ? 'danger' : 'success')
                    ->action(function ($record) {
                        $record->update([
                            'active' => ! $record->active,
                            'active_from' => ! $record->active ? now() : $record->active_from,
                        ]);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish Selected')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'active' => true,
                                    'active_from' => now(),
                                ]);
                            });
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('unpublish')
                        ->label('Unpublish Selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['active' => false]);
                            });
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Story Information')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('title')
                                        ->weight(FontWeight::Bold)
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                                    Infolists\Components\TextEntry::make('category.name')
                                        ->badge()
                                        ->color('primary'),

                                    Infolists\Components\TextEntry::make('tags.name')
                                        ->badge()
                                        ->separator(','),
                                ]),

                            Infolists\Components\ImageEntry::make('image')
                                ->disk('public')
                                ->height(200)
                                ->width(300),
                        ])->from('lg'),
                    ]),

                Infolists\Components\Section::make('Content')
                    ->schema([
                        Infolists\Components\TextEntry::make('excerpt')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('content')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Publishing & Analytics')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'published' => 'success',
                                        'scheduled' => 'warning',
                                        'expired' => 'danger',
                                        'draft' => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('views')
                                    ->numeric()
                                    ->icon('heroicon-o-eye'),

                                Infolists\Components\TextEntry::make('reading_time_minutes')
                                    ->formatStateUsing(fn ($state) => $state.' minutes'),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('active_from')
                                    ->label('Published From')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('active_until')
                                    ->label('Published Until')
                                    ->dateTime()
                                    ->placeholder('No expiry date'),

                                Infolists\Components\TextEntry::make('average_rating')
                                    ->label('Average Rating')
                                    ->formatStateUsing(function ($record) {
                                        $rating = $record->average_rating;
                                        $count = $record->total_ratings;

                                        return $rating > 0 ? number_format($rating, 1).' ★ ('.$count.' ratings)' : 'No ratings yet';
                                    }),
                            ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Add relations here if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStories::route('/'),
            'create' => Pages\CreateStory::route('/create'),
            'view' => Pages\ViewStory::route('/{record}'),
            'edit' => Pages\EditStory::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::count() > 10 ? 'warning' : 'primary';
    }
}
