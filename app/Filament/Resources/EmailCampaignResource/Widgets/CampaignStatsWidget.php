<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailCampaignResource\Widgets;

use App\Models\EmailLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CampaignStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalSent = EmailLog::where('status', EmailLog::STATUS_SENT)->count();
        $totalOpened = EmailLog::whereNotNull('opened_at')->count();
        $totalClicked = EmailLog::whereNotNull('clicked_at')->count();
        $totalFailed = EmailLog::where('status', EmailLog::STATUS_FAILED)->count();

        $openRate = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 1) : 0;
        $clickRate = $totalSent > 0 ? round(($totalClicked / $totalSent) * 100, 1) : 0;

        return [
            Stat::make('إجمالي الرسائل المرسلة', number_format($totalSent))
                ->description('جميع الرسائل المرسلة بنجاح')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('success'),

            Stat::make('معدل الفتح', $openRate . '%')
                ->description($totalOpened . ' رسالة تم فتحها')
                ->descriptionIcon('heroicon-m-envelope-open')
                ->color('info'),

            Stat::make('معدل النقر', $clickRate . '%')
                ->description($totalClicked . ' رسالة تم النقر عليها')
                ->descriptionIcon('heroicon-m-cursor-arrow-rays')
                ->color('warning'),

            Stat::make('الرسائل الفاشلة', number_format($totalFailed))
                ->description('رسائل فشل إرسالها')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
