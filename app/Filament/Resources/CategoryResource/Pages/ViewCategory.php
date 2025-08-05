<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Log;

class ViewCategory extends ViewRecord
{
    protected static string $resource = CategoryResource::class;

    /**
     * ✅ FIXED: Added proper type hint for getRecord()
     */
    public function getRecord(): Category
    {
        /** @var Category $record */
        $record = parent::getRecord();
        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    /**
     * ✅ FIXED: Override the infolist to fix the Recent Stories section
     */
    public function infolist(Infolist $infolist): Infolist
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
                                    ->getStateUsing(fn($record) => $record->stories()->count())
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('active_stories_count')
                                    ->label('Active Stories')
                                    ->getStateUsing(fn($record) => $record->stories()->where('active', true)->count())
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('total_views')
                                    ->label('Total Views')
                                    ->getStateUsing(fn($record) => number_format($record->stories()->sum('views')))
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

                // Recent Stories section removed due to display issues

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

    /**
     * ✅ CLEANED: Removed debugging methods as Recent Stories section was removed
     */
}
