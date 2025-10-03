<?php

namespace App\Filament\Pages;

use App\Models\PushNotification;
use App\Services\PushNotificationScheduler;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class NotificationDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static string $view = 'filament.pages.notification-dashboard';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Notification Dashboard';

    protected static ?string $title = 'Push Notifications Dashboard';

    protected static string $routePath = 'notification-dashboard';

    public static function getNavigationBadge(): ?string
    {
        $scheduled = \App\Models\PushNotification::where('status', \App\Models\PushNotification::STATUS_SCHEDULED)->count();
        return $scheduled > 0 ? (string) $scheduled : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public array $stats = [];
    public $recentNotifications = [];
    public $scheduledNotifications = [];

    public function mount(): void
    {
        $scheduler = app(PushNotificationScheduler::class);
        $this->stats = $scheduler->getStatistics();

        $this->recentNotifications = PushNotification::with('creator')
            ->where('status', PushNotification::STATUS_SENT)
            ->orderBy('sent_at', 'desc')
            ->limit(5)
            ->get();

        $this->scheduledNotifications = PushNotification::with('creator')
            ->where('status', PushNotification::STATUS_SCHEDULED)
            ->orderBy('scheduled_at', 'asc')
            ->limit(5)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_notification')
                ->label('New Notification')
                ->icon('heroicon-o-plus')
                ->url(route('filament.admin.resources.push-notifications.create'))
                ->color('primary'),

            Action::make('view_all')
                ->label('View All')
                ->icon('heroicon-o-queue-list')
                ->url(route('filament.admin.resources.push-notifications.index'))
                ->color('gray'),

            Action::make('send_test')
                ->label('Send Test')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Send Test Notification')
                ->modalDescription('This will send a test notification to all users.')
                ->action(function () {
                    try {
                        $notification = PushNotification::create([
                            'title' => '🧪 Test Notification',
                            'body' => 'This is a test notification sent at ' . now()->format('H:i'),
                            'target_type' => PushNotification::TARGET_ALL,
                            'status' => PushNotification::STATUS_DRAFT,
                            'created_by' => auth()->id(),
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                        ]);

                        app(PushNotificationScheduler::class)->sendNotification($notification);

                        Notification::make()
                            ->success()
                            ->title('Test Sent!')
                            ->body("Delivered to {$notification->success_count} devices")
                            ->send();

                        $this->mount(); // Refresh data
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Test Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
        ];
    }

    public function getViewData(): array
    {
        return [
            'stats' => $this->stats,
            'recentNotifications' => $this->recentNotifications,
            'scheduledNotifications' => $this->scheduledNotifications,
        ];
    }
}
