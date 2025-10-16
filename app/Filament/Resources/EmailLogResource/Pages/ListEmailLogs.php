<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailLogResource\Pages;

use App\Filament\Resources\EmailLogResource;
use App\Services\EmailService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEmailLogs extends ListRecords
{
    protected static string $resource = EmailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_statistics')
                ->label('إحصائيات البريد')
                ->icon('heroicon-o-chart-bar')
                ->color('primary')
                ->modalHeading('إحصائيات البريد الإلكتروني')
                ->modalContent(function () {
                    $emailService = app(EmailService::class);
                    $stats = $emailService->getStatistics();

                    return view('filament.pages.email-statistics', ['stats' => $stats]);
                })
                ->modalWidth('5xl')
                ->slideOver(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل'),

            'sent' => Tab::make('تم الإرسال')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'sent'))
                ->badge(fn() => \App\Models\EmailLog::where('status', 'sent')->count()),

            'pending' => Tab::make('قيد الانتظار')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending'))
                ->badge(fn() => \App\Models\EmailLog::where('status', 'pending')->count())
                ->badgeColor('warning'),

            'failed' => Tab::make('فشل')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'failed'))
                ->badge(fn() => \App\Models\EmailLog::where('status', 'failed')->count())
                ->badgeColor('danger'),

            'opened' => Tab::make('تم فتحه')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotNull('opened_at'))
                ->badge(fn() => \App\Models\EmailLog::whereNotNull('opened_at')->count())
                ->badgeColor('success'),

            'clicked' => Tab::make('تم النقر')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotNull('clicked_at'))
                ->badge(fn() => \App\Models\EmailLog::whereNotNull('clicked_at')->count())
                ->badgeColor('info'),
        ];
    }
}
