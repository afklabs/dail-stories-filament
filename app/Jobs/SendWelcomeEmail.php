<?php

namespace App\Jobs;

use App\Models\Member;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(
        public Member $member
    ) {}

    public function handle(EmailService $emailService): void
    {
        $emailService->sendWelcomeEmail($this->member);
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('Failed to send welcome email', [
            'member_id' => $this->member->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
