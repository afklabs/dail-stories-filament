<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Category Information')
                    ->description('Basic category details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $context, $state, Forms\Set $set) {
                                if ($context === 'create') {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                            ->helperText('URL-friendly version of the name (lowercase, no spaces)')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $set('slug', Str::slug($state));
                            }),

                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Brief description of this category'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Statistics')
                    ->description('Category analytics and metrics')
                    ->schema([
                        Forms\Components\Placeholder::make('stories_count')
                            ->label('Total Stories')
                            ->content(function ($record) {
                                return $record ? $record->stories()->count() : 0;
                            }),

                        Forms\Components\Placeholder::make('active_stories_count')
                            ->label('Active Stories')
                            ->content(function ($record) {
                                return $record ? $record->stories()->where('active', true)->count() : 0;
                            }),

                        Forms\Components\Placeholder::make('total_views')
                            ->label('Total Views')
                            ->content(function ($record) {
                                return $record ? number_format($record->stories()->sum('views')) : 0;
                            }),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(function ($record) {
                                return $record ? $record->created_at->format('M j, Y H:i') : 'Not created yet';
                            }),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Slug copied')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\TextColumn::make('stories_count')
                    ->label('Stories')
                    ->counts('stories')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('active_stories_count')
                    ->label('Active')
                    ->counts([
                        'stories' => fn (Builder $query) => $query->where('active', true)
                    ])
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('total_views')
                    ->label('Total Views')
                    ->getStateUsing(function ($record) {
                        return $record->stories()->sum('views');
                    })
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('has_stories')
                    ->label('Has Stories')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('stories')
                    ),

                Filter::make('has_active_stories')
                    ->label('Has Active Stories')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('stories', function (Builder $query) {
                            $query->where('active', true);
                        })
                    ),

                Filter::make('popular')
                    ->label('Popular (5+ Stories)')
                    ->query(fn (Builder $query): Builder => 
                        $query->withCount('stories')
                              ->having('stories_count', '>=', 5)
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription(function ($record) {
                        $storiesCount = $record->stories()->count();
                        if ($storiesCount > 0) {
                            return "This category has {$storiesCount} stories. Deleting it will also delete all associated stories. Are you sure?";
                        }
                        return 'Are you sure you want to delete this category?';
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalDescription('Are you sure you want to delete these categories? This will also delete all associated stories.'),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Category Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                                Infolists\Components\TextEntry::make('slug')
                                    ->badge()
                                    ->color('gray')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('description')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('stories_count')
                                    ->label('Total Stories')
                                    ->getStateUsing(fn ($record) => $record->stories()->count())
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('active_stories_count')
                                    ->label('Active Stories')
                                    ->getStateUsing(fn ($record) => $record->stories()->where('active', true)->count())
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('total_views')
                                    ->label('Total Views')
                                    ->getStateUsing(fn ($record) => number_format($record->stories()->sum('views')))
                                    ->badge()
                                    ->color('warning'),

                                Infolists\Components\TextEntry::make('avg_reading_time')
                                    ->label('Avg Reading Time')
                                    ->getStateUsing(function ($record) {
                                        $avg = $record->stories()->avg('reading_time_minutes');
                                        return $avg ? round($avg, 1) . ' min' : 'N/A';
                                    })
                                    ->badge()
                                    ->color('info'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Recent Stories')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('recent_stories')
                            ->label('')
                            ->getStateUsing(function ($record) {
                                return $record->stories()
                                    ->orderBy('created_at', 'desc')
                                    ->limit(5)
                                    ->get()
                                    ->map(function ($story) {
                                        return [
                                            'title' => $story->title,
                                            'status' => $story->active ? 'Published' : 'Draft',
                                            'views' => number_format($story->views),
                                            'created_at' => $story->created_at->format('M j, Y'),
                                        ];
                                    })->toArray();
                            })
                            ->schema([
                                Infolists\Components\Grid::make(4)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('title')
                                            ->weight(FontWeight::Medium),
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (string $state): string => 
                                                $state === 'Published' ? 'success' : 'gray'
                                            ),
                                        Infolists\Components\TextEntry::make('views'),
                                        Infolists\Components\TextEntry::make('created_at'),
                                    ]),
                            ])
                            ->visible(fn ($record) => $record->stories()->exists()),
                    ])
                    ->visible(fn ($record) => $record->stories()->exists()),

                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime(),
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible(),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'view' => Pages\ViewCategory::route('/{record}'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::count() > 5 ? 'success' : 'primary';
    }
}