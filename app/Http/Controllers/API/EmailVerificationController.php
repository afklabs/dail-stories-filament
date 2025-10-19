<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\EmailService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

/**
 * Email Verification Controller
 * 
 * Handles email verification process for members including:
 * - Email verification via signed URL
 * - Resending verification emails
 * - Checking verification status
 * 
 * @package App\Http\Controllers\API
 */
class EmailVerificationController extends Controller
{
    /**
     * Maximum resend attempts per minute
     */
    private const RATE_LIMIT_RESEND = 3;

    /**
     * Verification link expiration time in minutes (24 hours)
     */
    private const VERIFICATION_LINK_EXPIRES = 1440;

    /**
     * Constructor with dependency injection
     *
     * @param EmailService $emailService Email service for sending verification emails
     */
    public function __construct(
        private EmailService $emailService
    ) {}

    /**
     * Verify member email using signed URL
     * 
     * Validates the verification link and marks the email as verified
     * if all checks pass (signature, expiration, hash).
     *
     * @param Request $request HTTP request containing verification parameters
     * @return JsonResponse Verification result
     */
    public function verify(Request $request): JsonResponse
    {
        try {
            // Validate required parameters
            $request->validate([
                'id' => 'required|integer',
                'hash' => 'required|string',
                'expires' => 'required|integer',
                'signature' => 'required|string',
            ]);

            // Find member by ID
            $member = Member::findOrFail($request->id);

            // Check if email is already verified
            if ($member->hasVerifiedEmail()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email already verified',
                    'data' => ['verified' => true],
                ], 200);
            }

            // Verify hash matches the member's email
            if (!hash_equals(
                (string) $request->hash,
                sha1($member->getEmailForVerification())
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification link',
                ], 403);
            }

            // Check if verification link has expired
            if ($request->expires < now()->timestamp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification link has expired. Please request a new one.',
                ], 410);
            }

            // Verify the URL signature to prevent tampering
            $url = URL::temporarySignedRoute(
                'api.v1.email.verify',
                now()->addMinutes(self::VERIFICATION_LINK_EXPIRES),
                [
                    'id' => $member->id,
                    'hash' => sha1($member->getEmailForVerification()),
                ]
            );

            if (!hash_equals(
                (string) $request->signature,
                hash_hmac('sha256', $url, config('app.key'))
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification signature',
                ], 403);
            }

            // Mark email as verified
            $member->markEmailAsVerified();

            // Fire Laravel's verified event
            event(new Verified($member));

            // Log successful verification
            Log::info('Email verified successfully', [
                'member_id' => $member->id,
                'email' => $member->email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully',
                'data' => [
                    'verified' => true,
                    'verified_at' => $member->email_verified_at->toISOString(),
                    'member' => [
                        'id' => $member->id,
                        'email' => $member->email,
                        'name' => $member->name,
                    ],
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Email verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Resend verification email to authenticated member
     * 
     * Rate limited to prevent abuse (3 requests per minute).
     * Only sends if email is not already verified.
     *
     * @param Request $request HTTP request with authenticated user
     * @return JsonResponse Result of resend attempt
     */
    public function resend(Request $request): JsonResponse
    {
        try {
            // Apply rate limiting per user
            $rateLimitKey = 'verify-email-resend:' . $request->user()->id;

            if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_RESEND)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again in ' .
                        ceil(RateLimiter::availableIn($rateLimitKey) / 60) . ' minutes.',
                ], 429);
            }

            $member = $request->user();

            // Check if email is already verified
            if ($member->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email is already verified',
                ], 400);
            }

            // Send verification email
            $this->sendVerificationEmail($member);

            // Record rate limit attempt
            RateLimiter::hit($rateLimitKey, 60); // 1 minute cooldown

            // Log successful resend
            Log::info('Verification email resent', [
                'member_id' => $member->id,
                'email' => $member->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Verification email sent successfully',
                'data' => [
                    'sent' => true,
                    'email' => $member->email,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Resend verification error', [
                'member_id' => $request->user()->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email. Please try again.',
            ], 500);
        }
    }

    /**
     * Check email verification status for authenticated member
     *
     * @param Request $request HTTP request with authenticated user
     * @return JsonResponse Current verification status
     */
    public function status(Request $request): JsonResponse
    {
        $member = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Email verification status retrieved',
            'data' => [
                'verified' => $member->hasVerifiedEmail(),
                'email' => $member->email,
                'verified_at' => $member->email_verified_at?->toISOString(),
            ],
        ], 200);
    }

    /**
     * Send verification email to member
     * 
     * Generates a signed URL and sends it via EmailService.
     *
     * @param Member $member Member to send verification email to
     * @return void
     */
    private function sendVerificationEmail(Member $member): void
    {
        // Generate signed verification URL
        $verificationUrl = $this->generateVerificationUrl($member);

        // Send email using EmailService
        $this->emailService->send(
            emailType: 'email_verification',
            recipientEmail: $member->email,
            subject: 'تأكيد البريد الإلكتروني - قصصي',
            view: 'emails.verify-email',
            data: [
                'member' => $member,
                'verificationUrl' => $verificationUrl,
                'expiresIn' => self::VERIFICATION_LINK_EXPIRES / 60, // Convert to hours
            ],
            memberId: $member->id,
            metadata: [
                'verification_url' => $verificationUrl,
                'expires_at' => now()->addMinutes(self::VERIFICATION_LINK_EXPIRES),
            ]
        );
    }

    /**
     * Generate signed verification URL for member
     * 
     * Creates a temporary signed route that expires after 24 hours.
     *
     * @param Member $member Member to generate URL for
     * @return string Signed verification URL
     */
    private function generateVerificationUrl(Member $member): string
    {
        return URL::temporarySignedRoute(
            'api.v1.email.verify',
            now()->addMinutes(self::VERIFICATION_LINK_EXPIRES),
            [
                'id' => $member->id,
                'hash' => sha1($member->getEmailForVerification()),
            ]
        );
    }
}
