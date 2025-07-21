<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class MemberOverviewWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '120s';
    protected static bool $isLazy = true;

    protected function getStats(): array
    {
        try {
            // Cache expensive queries for 5 minutes
            $totalMembers = cache()->remember('dashboard.total_members', 300, function() {
                return Member::count();
            });

            $activeMembers = cache()->remember('dashboard.active_members', 300, function() {
                return Member::active()->count();
            });

            $newMembersThisMonth = cache()->remember('dashboard.new_members_month', 300, function() {
                return Member::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count();
            });

            $verifiedMembers = cache()->remember('dashboard.verified_members', 300, function() {
                return Member::verified()->count();
            });

            // Calculate trends
            $lastMonthNew = Member::whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->count();
            
            $growthTrend = $lastMonthNew > 0 
                ? round((($newMembersThisMonth - $lastMonthNew) / $lastMonthNew) * 100, 1)
                : 0;

            return [
                Stat::make('Total Members', Number::format($totalMembers))
                    ->description('All registered users')
                    ->descriptionIcon('heroicon-m-users')
                    ->color('primary'),

                Stat::make('Active Members', Number::format($activeMembers))
                    ->description($this->getPercentage($activeMembers, $totalMembers) . '% of total')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success'),

                Stat::make('New This Month', Number::format($newMembersThisMonth))
                    ->description($growthTrend >= 0 ? "+{$growthTrend}% from last month" : "{$growthTrend}% from last month")
                    ->descriptionIcon($growthTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                    ->color($growthTrend >= 0 ? 'success' : 'danger'),

                Stat::make('Email Verified', Number::format($verifiedMembers))
                    ->description($this->getPercentage($verifiedMembers, $totalMembers) . '% verified')
                    ->descriptionIcon('heroicon-m-shield-check')
                    ->color('warning'),
            ];
        } catch (\Exception $e) {
            \Log::error('Dashboard MemberOverview widget error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                Stat::make('Error', 'Data unavailable')
                    ->description('Please refresh the page')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }
    }

    private function getPercentage(int $part, int $total): string
    {
        if ($total === 0) return '0';
        return (string) round(($part / $total) * 100, 1);
    }
}