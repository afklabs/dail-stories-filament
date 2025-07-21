<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Models\Tag;
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

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-o-hashtag';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tag Information')
                    ->description('Basic tag details')
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
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Statistics')
                    ->description('Tag usage analytics and metrics')
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
                    ->weight(FontWeight::Bold)
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Slug copied')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('stories_count')
                    ->label('Stories')
                    ->getStateUsing(function ($record) {
                        return $record->stories()->count();
                    })
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('active_stories_count')
                    ->label('Active')
                    ->getStateUsing(function ($record) {
                        return $record->stories()->where('active', true)->count();
                    })
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('total_views')
                    ->label('Total Views')
                    ->getStateUsing(function ($record) {
                        return $record->stories()->sum('views');
                    })
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('avg_reading_time')
                    ->label('Avg Reading Time')
                    ->getStateUsing(function ($record) {
                        $avg = $record->stories()->avg('reading_time_minutes');
                        return $avg ? round($avg, 1) . ' min' : 'N/A';
                    })
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->label('Popular (3+ Stories)')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('stories', null, '>=', 3)
                    ),

                Filter::make('highly_used')
                    ->label('Highly Used (10+ Stories)')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('stories', null, '>=', 10)
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
                            return "This tag is used in {$storiesCount} stories. Deleting it will remove the tag from all stories. Are you sure?";
                        }
                        return 'Are you sure you want to delete this tag?';
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalDescription('Are you sure you want to delete these tags? They will be removed from all associated stories.'),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Tag Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('slug')
                                    ->badge()
                                    ->color('gray')
                                    ->copyable(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Usage Statistics')
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

                Infolists\Components\Section::make('Tagged Stories')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('recent_stories')
                            ->label('')
                            ->getStateUsing(function ($record) {
                                return $record->stories()
                                    ->with('category')
                                    ->orderBy('created_at', 'desc')
                                    ->limit(10)
                                    ->get()
                                    ->map(function ($story) {
                                        return [
                                            'title' => $story->title,
                                            'category' => $story->category->name ?? 'No Category',
                                            'status' => $story->active ? 'Published' : 'Draft',
                                            'views' => number_format($story->views),
                                            'created_at' => $story->created_at->format('M j, Y'),
                                        ];
                                    })->toArray();
                            })
                            ->schema([
                                Infolists\Components\Grid::make(5)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('title')
                                            ->weight(FontWeight::Medium)
                                            ->limit(40),
                                        Infolists\Components\TextEntry::make('category')
                                            ->badge()
                                            ->color('secondary'),
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
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'view' => Pages\ViewTag::route('/{record}'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::count() > 10 ? 'success' : 'primary';
    }
}