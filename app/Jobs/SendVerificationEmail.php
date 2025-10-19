<?php

namespace App\Jobs;

use App\Models\Member;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class SendVerificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(
        public Member $member
    ) {}

    public function handle(EmailService $emailService): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'api.v1.email.verify',
            now()->addMinutes(1440),
            [
                'id' => $this->member->id,
                'hash' => sha1($this->member->getEmailForVerification()),
            ]
        );

        $emailService->sendEmailVerification($this->member, $verificationUrl);
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('Failed to send verification email', [
            'member_id' => $this->member->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
