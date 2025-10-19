<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailLog;
use App\Models\Member;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Email Service
 * 
 * Centralized service for sending emails with tracking
 */
class EmailService
{
    /**
     * Send email with tracking
     * 
     * @param string $emailType Type of email (welcome, password_reset, promotional)
     * @param string $recipientEmail Recipient email address
     * @param string $subject Email subject
     * @param string $view Blade view for email
     * @param array $data Data to pass to view
     * @param int|null $memberId Member ID if applicable
     * @param int|null $sentByUserId User ID who sent the email
     * @param array $metadata Additional metadata
     * @return EmailLog
     */
    public function send(
        string $emailType,
        string $recipientEmail,
        string $subject,
        string $view,
        array $data = [],
        ?int $memberId = null,
        ?int $sentByUserId = null,
        array $metadata = []
    ): EmailLog {
        // Generate unique tracking ID
        $trackingId = Str::uuid()->toString();

        // Add tracking ID to data
        $data['trackingId'] = $trackingId;

        // Create email log entry
        $emailLog = EmailLog::create([
            'email_type' => $emailType,
            'recipient_email' => $recipientEmail,
            'member_id' => $memberId,
            'sent_by_user_id' => $sentByUserId,
            'subject' => $subject,
            'status' => EmailLog::STATUS_PENDING,
            'tracking_id' => $trackingId,
            'metadata' => $metadata,
        ]);

        try {
            // Send email
            Mail::send($view, $data, function ($message) use ($recipientEmail, $subject) {
                $message->to($recipientEmail)
                    ->subject($subject);
            });

            // Mark as sent
            $emailLog->markAsSent();

            Log::info('Email sent successfully', [
                'email_log_id' => $emailLog->id,
                'type' => $emailType,
                'recipient' => $recipientEmail,
            ]);
        } catch (\Exception $e) {
            // Mark as failed
            $emailLog->markAsFailed($e->getMessage());

            Log::error('Email sending failed', [
                'email_log_id' => $emailLog->id,
                'type' => $emailType,
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);
        }

        return $emailLog;
    }

    /**
     * Send welcome email to new member
     */
    public function sendWelcomeEmail(Member $member): EmailLog
    {
        return $this->send(
            emailType: EmailLog::TYPE_WELCOME,
            recipientEmail: $member->email,
            subject: 'مرحباً بك في قصصي!',
            view: 'emails.welcome',
            data: [
                'memberName' => $member->name,
                'appUrl' => config('app.frontend_url'),
            ],
            memberId: $member->id
        );
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(Member $member, string $resetToken): EmailLog
    {
        $resetUrl = config('app.frontend_url') . '/reset-password?token=' . $resetToken . '&email=' . urlencode($member->email);

        return $this->send(
            emailType: EmailLog::TYPE_PASSWORD_RESET,
            recipientEmail: $member->email,
            subject: 'إعادة تعيين كلمة المرور',
            view: 'emails.password-reset',
            data: [
                'memberName' => $member->name,
                'resetUrl' => $resetUrl,
                'expiresAt' => now()->addHours(2)->format('h:i A'),
            ],
            memberId: $member->id
        );
    }

    /**
     * Send promotional email
     */
    public function sendPromotionalEmail(
        Member $member,
        string $subject,
        string $htmlContent,
        ?int $sentByUserId = null,
        ?int $campaignId = null
    ): EmailLog {
        return $this->send(
            emailType: EmailLog::TYPE_PROMOTIONAL,
            recipientEmail: $member->email,
            subject: $subject,
            view: 'emails.promotional',
            data: [
                'memberName' => $member->name,
                'content' => $htmlContent,
            ],
            memberId: $member->id,
            sentByUserId: $sentByUserId,
            metadata: $campaignId ? ['campaign_id' => $campaignId] : []
        );
    }

    /**
     * Get email statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $query = EmailLog::query();

        if (isset($filters['email_type'])) {
            $query->where('email_type', $filters['email_type']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $total = $query->count();
        $sent = (clone $query)->where('status', EmailLog::STATUS_SENT)->count();
        $failed = (clone $query)->where('status', EmailLog::STATUS_FAILED)->count();
        $opened = (clone $query)->whereNotNull('opened_at')->count();
        $clicked = (clone $query)->whereNotNull('clicked_at')->count();

        return [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'pending' => $total - $sent - $failed,
            'opened' => $opened,
            'clicked' => $clicked,
            'delivery_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
            'open_rate' => $sent > 0 ? round(($opened / $sent) * 100, 2) : 0,
            'click_rate' => $sent > 0 ? round(($clicked / $sent) * 100, 2) : 0,
        ];
    }


    /**
     * Send email verification email
     * 
     * @param Member $member
     * @param string $verificationUrl
     * @return EmailLog
     */
    public function sendEmailVerification(Member $member, string $verificationUrl): EmailLog
    {
        return $this->send(
            emailType: 'email_verification',
            recipientEmail: $member->email,
            subject: 'تأكيد البريد الإلكتروني - قصصي',
            view: 'emails.verify-email',
            data: [
                'member' => $member,
                'verificationUrl' => $verificationUrl,
            ],
            memberId: $member->id,
            metadata: [
                'verification_url' => $verificationUrl,
            ]
        );
    }
}
