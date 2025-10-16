<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmailCampaign;
use App\Models\Member;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function __construct(
        public int $campaignId,
        public int $memberId,
        public int $sentByUserId
    ) {}

    public function handle(EmailService $emailService): void
    {
        try {
            $campaign = EmailCampaign::find($this->campaignId);
            $member = Member::find($this->memberId);

            if (!$campaign || !$member) {
                Log::warning('Campaign or member not found', [
                    'campaign_id' => $this->campaignId,
                    'member_id' => $this->memberId,
                ]);
                return;
            }

            // استخدم الـ body من الـ campaign مباشرة
            $emailService->sendPromotionalEmail(
                member: $member,
                subject: $campaign->subject,
                htmlContent: $campaign->body, // ← هنا المشكلة كانت
                sentByUserId: $this->sentByUserId,
                campaignId: $campaign->id
            );

            $campaign->increment('sent_count');

            Log::info('Campaign email sent', [
                'campaign_id' => $this->campaignId,
                'member_id' => $this->memberId,
            ]);
        } catch (\Exception $e) {
            $campaign = EmailCampaign::find($this->campaignId);
            if ($campaign) {
                $campaign->increment('failed_count');
            }

            Log::error('Campaign email failed', [
                'campaign_id' => $this->campaignId,
                'member_id' => $this->memberId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Campaign email job failed permanently', [
            'campaign_id' => $this->campaignId,
            'member_id' => $this->memberId,
            'error' => $exception->getMessage(),
        ]);

        $campaign = EmailCampaign::find($this->campaignId);
        if ($campaign) {
            $campaign->increment('failed_count');
        }
    }
}
