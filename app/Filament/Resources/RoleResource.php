<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Role Information')
                    ->description('Define the role name and basic details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(125)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $context, $state, Forms\Set $set) {
                                if ($context === 'create') {
                                    $set('name', Str::slug($state, '_'));
                                }
                            })
                            ->helperText('Use lowercase with underscores (e.g., content_manager)')
                            ->rules(['regex:/^[a-z0-9_]+$/']),

                        Forms\Components\TextInput::make('guard_name')
                            ->default('web')
                            ->required()
                            ->maxLength(125)
                            ->disabled()
                            ->helperText('Guard name for this role (usually "web")'),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Optional description of what this role can do'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Permissions')
                    ->description('Select permissions for this role')
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->relationship('permissions', 'name')
                            ->searchable()
                            ->bulkToggleable()
                            ->gridDirection('row')
                            ->columns(3)
                            ->columnSpanFull()
                            ->options(function () {
                                return Permission::all()
                                    ->groupBy(function ($permission) {
                                        // Group by resource type (e.g., story, member, category)
                                        $parts = explode('_', $permission->name);
                                        if (count($parts) >= 2) {
                                            return ucfirst(end($parts));
                                        }

                                        return 'Other';
                                    })
                                    ->map(function ($permissions, $group) {
                                        return $permissions->pluck('name', 'id')->toArray();
                                    })
                                    ->collapse()
                                    ->toArray();
                            })
                            ->descriptions(function () {
                                return Permission::all()->pluck('name', 'id')
                                    ->map(function ($name) {
                                        // Create human-readable descriptions
                                        return ucwords(str_replace('_', ' ', $name));
                                    })
                                    ->toArray();
                            }),
                    ]),

                Forms\Components\Section::make('Statistics')
                    ->description('Role usage and information')
                    ->schema([
                        Forms\Components\Placeholder::make('users_count')
                            ->label('Users with this role')
                            ->content(function ($record) {
                                return $record ? $record->users()->count() : 0;
                            }),

                        Forms\Components\Placeholder::make('permissions_count')
                            ->label('Total permissions')
                            ->content(function ($record) {
                                return $record ? $record->permissions()->count() : 0;
                            }),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(function ($record) {
                                return $record ? $record->created_at?->format('M j, Y \a\t g:i A') : 'Not created yet';
                            }),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Last modified')
                            ->content(function ($record) {
                                return $record ? $record->updated_at?->format('M j, Y \a\t g:i A') : 'Not modified yet';
                            }),
                    ])
                    ->columns(2)
                    ->hidden(fn (string $context): bool => $context === 'create'),
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
                    ->copyable(),

                Tables\Columns\TextColumn::make('guard_name')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 50 ? $state : null;
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('guard_name')
                    ->options([
                        'web' => 'Web',
                        'api' => 'API',
                    ]),

                Tables\Filters\Filter::make('has_users')
                    ->label('Has Users')
                    ->query(fn (Builder $query): Builder => $query->has('users'))
                    ->toggle(),

                Tables\Filters\Filter::make('system_roles')
                    ->label('System Roles')
                    ->query(fn (Builder $query): Builder => $query->whereIn('name', ['super_admin', 'admin', 'moderator']))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Role')
                    ->modalDescription('Are you sure you want to delete this role? Users with this role will lose their permissions.')
                    ->modalSubmitActionLabel('Yes, delete role'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Role Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->weight(FontWeight::Bold)
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('guard_name')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('users_count')
                                    ->label('Users with this role')
                                    ->formatStateUsing(fn ($record) => $record->users()->count()),

                                Infolists\Components\TextEntry::make('permissions_count')
                                    ->label('Total permissions')
                                    ->formatStateUsing(fn ($record) => $record->permissions()->count()),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ]),

                Infolists\Components\Section::make('Permissions')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('permissions')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->badge()
                                    ->color('success'),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Users with this Role')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('users')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->weight(FontWeight::Bold),
                                Infolists\Components\TextEntry::make('email')
                                    ->color('gray'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
