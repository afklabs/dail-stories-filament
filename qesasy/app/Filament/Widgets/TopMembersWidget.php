<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopMembersWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = true;

    protected function getTableHeading(): string
    {
        return 'Most Engaged Members';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(function ($record) {
                        if ($record->avatar) {
                            return asset('storage/members/avatars/'.$record->avatar);
                        }

                        return 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($record->email))).'?d=mp&s=80';
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->label('Member')
                    ->searchable(false)
                    ->sortable(false)
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->searchable(false),

                Tables\Columns\TextColumn::make('recent_views_count')
                    ->label('Recent Views')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => number_format($state)),

                Tables\Columns\TextColumn::make('recent_interactions_count')
                    ->label('Interactions')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => number_format($state)),

                Tables\Columns\TextColumn::make('completion_rate')
                    ->label('Completion Rate')
                    ->formatStateUsing(function ($record) {
                        try {
                            $stats = $record->getStats();

                            return $stats['completion_rate'].'%';
                        } catch (\Exception $e) {
                            return 'N/A';
                        }
                    })
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('reading_streak')
                    ->label('Streak')
                    ->formatStateUsing(function ($record) {
                        try {
                            $stats = $record->getStats();
                            $days = $stats['reading_streak_days'] ?? 0;

                            return $days.' day'.($days !== 1 ? 's' : '');
                        } catch (\Exception $e) {
                            return '0 days';
                        }
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Active')
                    ->dateTime()
                    ->since()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_profile')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.members.view', $record))
                    ->openUrlInNewTab(),
            ])
            ->paginated(false)
            ->poll('300s')
            ->defaultSort('recent_views_count', 'desc');
    }

    protected function getTableQuery(): Builder
    {
        try {
            return Member::query()
                ->select([
                    'id',
                    'name',
                    'email',
                    'avatar',
                    'last_login_at',
                    'status',
                ])
                ->active()
                ->withCount([
                    'storyViews as recent_views_count' => function ($query) {
                        $query->where('created_at', '>=', now()->subDays(30));
                    },
                    'interactions as recent_interactions_count' => function ($query) {
                        $query->where('created_at', '>=', now()->subDays(30));
                    },
                ])
                ->having('recent_views_count', '>', 0)
                ->orderBy('recent_views_count', 'desc')
                ->limit(8);
        } catch (\Exception $e) {
            \Log::error('TopMembersWidget query error', [
                'error' => $e->getMessage(),
            ]);

            return Member::query()->whereRaw('1 = 0');
        }
    }
}
