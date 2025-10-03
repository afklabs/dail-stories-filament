<?php

namespace App\Console\Commands;

use App\Services\PushNotificationScheduler;
use Illuminate\Console\Command;

class SendScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-scheduled 
                            {--dry-run : Show notifications that would be sent without actually sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all scheduled push notifications that are ready to be sent';

    protected PushNotificationScheduler $scheduler;

    /**
     * Create a new command instance.
     */
    public function __construct(PushNotificationScheduler $scheduler)
    {
        parent::__construct();
        $this->scheduler = $scheduler;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🔍 Checking for scheduled notifications...');

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🧪 DRY RUN MODE - No notifications will be sent');

            $upcoming = $this->scheduler->getUpcomingNotifications(20);

            if ($upcoming->isEmpty()) {
                $this->info('✅ No scheduled notifications ready to send.');
                return Command::SUCCESS;
            }

            $this->table(
                ['ID', 'Title', 'Target', 'Scheduled At', 'Status'],
                $upcoming->map(fn($n) => [
                    $n->id,
                    \Illuminate\Support\Str::limit($n->title, 30),
                    $n->getTargetTypeLabel(),
                    $n->scheduled_at->format('Y-m-d H:i'),
                    $n->time_until_send ?? 'Ready',
                ])
            );

            return Command::SUCCESS;
        }

        // Process scheduled notifications
        $result = $this->scheduler->processScheduledNotifications();

        if ($result['total'] === 0) {
            $this->info('✅ No scheduled notifications ready to send.');
            return Command::SUCCESS;
        }

        // Display results
        $this->newLine();
        $this->info("📊 Processing Results:");
        $this->line("   Total processed: {$result['total']}");
        $this->line("   ✅ Successfully sent: {$result['sent']}");

        if ($result['failed'] > 0) {
            $this->error("   ❌ Failed: {$result['failed']}");
        }

        $this->newLine();
        $this->info('✅ Scheduled notifications processing complete!');

        return Command::SUCCESS;
    }
}
