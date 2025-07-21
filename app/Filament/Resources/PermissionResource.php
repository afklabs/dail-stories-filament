<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
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

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Permission Information')
                    ->description('Define the permission name and details')
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
                            ->helperText('Use lowercase with underscores (e.g., create_stories, edit_members)')
                            ->rules(['regex:/^[a-z0-9_]+$/'])
                            ->placeholder('e.g., view_stories, create_members'),

                        Forms\Components\TextInput::make('guard_name')
                            ->default('web')
                            ->required()
                            ->maxLength(125)
                            ->disabled()
                            ->helperText('Guard name for this permission (usually "web")'),

                        Forms\Components\Select::make('category')
                            ->label('Permission Category')
                            ->options([
                                'stories' => 'Stories',
                                'members' => 'Members',
                                'categories' => 'Categories',
                                'tags' => 'Tags',
                                'users' => 'Users',
                                'roles' => 'Roles',
                                'permissions' => 'Permissions',
                                'analytics' => 'Analytics',
                                'system' => 'System',
                                'settings' => 'Settings',
                            ])
                            ->helperText('Group this permission belongs to')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->placeholder('Describe what this permission allows users to do')
                            ->helperText('Optional description of this permission'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Assign to Roles')
                    ->description('Select roles that should have this permission')
                    ->schema([
                        Forms\Components\CheckboxList::make('roles')
                            ->relationship('roles', 'name')
                            ->searchable()
                            ->bulkToggleable()
                            ->gridDirection('row')
                            ->columns(3)
                            ->columnSpanFull()
                            ->descriptions(function () {
                                return Role::all()->pluck('name', 'id')
                                    ->map(function ($name) {
                                        return ucwords(str_replace('_', ' ', $name));
                                    })
                                    ->toArray();
                            }),
                    ]),

                Forms\Components\Section::make('Statistics')
                    ->description('Permission usage information')
                    ->schema([
                        Forms\Components\Placeholder::make('roles_count')
                            ->label('Roles with this permission')
                            ->content(function ($record) {
                                return $record ? $record->roles()->count() : 0;
                            }),

                        Forms\Components\Placeholder::make('users_count')
                            ->label('Users with this permission')
                            ->content(function ($record) {
                                return $record ? $record->users()->count() : 0;
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

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'stories' => 'success',
                        'members' => 'info',
                        'categories' => 'warning',
                        'users' => 'primary',
                        'system' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Other'),

                Tables\Columns\TextColumn::make('guard_name')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('roles_count')
                    ->counts('roles')
                    ->label('Roles')
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

                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'stories' => 'Stories',
                        'members' => 'Members',
                        'categories' => 'Categories',
                        'tags' => 'Tags',
                        'users' => 'Users',
                        'roles' => 'Roles',
                        'permissions' => 'Permissions',
                        'analytics' => 'Analytics',
                        'system' => 'System',
                        'settings' => 'Settings',
                    ]),

                Tables\Filters\Filter::make('has_roles')
                    ->label('Assigned to Roles')
                    ->query(fn (Builder $query): Builder => $query->has('roles'))
                    ->toggle(),

                Tables\Filters\Filter::make('system_permissions')
                    ->label('System Permissions')
                    ->query(fn (Builder $query): Builder => $query->where('name', 'like', 'view_any_%')
                        ->orWhere('name', 'like', 'delete_any_%')
                        ->orWhere('name', 'like', 'force_%'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Permission')
                    ->modalDescription('Are you sure you want to delete this permission? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete permission'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('name')
            ->groups([
                Tables\Grouping\Group::make('category')
                    ->label('Category')
                    ->collapsible(),
                Tables\Grouping\Group::make('guard_name')
                    ->label('Guard')
                    ->collapsible(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Permission Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->weight(FontWeight::Bold)
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('guard_name')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('category')
                                    ->badge()
                                    ->color('success')
                                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Other'),

                                Infolists\Components\TextEntry::make('roles_count')
                                    ->label('Assigned to roles')
                                    ->formatStateUsing(fn ($record) => $record->roles()->count()),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ]),

                Infolists\Components\Section::make('Assigned Roles')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('roles')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->badge()
                                    ->color('success'),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Users with this Permission')
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
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'view' => Pages\ViewPermission::route('/{record}'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
