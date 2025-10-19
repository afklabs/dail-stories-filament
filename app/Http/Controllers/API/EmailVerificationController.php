<?php

// File: app/Http/Controllers/API/EmailVerificationController.php

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

class EmailVerificationController extends Controller
{
    private const RATE_LIMIT_RESEND = 3; // 3 requests per minute
    private const VERIFICATION_LINK_EXPIRES = 1440; // 24 hours in minutes

    public function __construct(
        private EmailService $emailService
    ) {}

    /**
     * Verify email with token
     *
     * @param Request $request
     * @return JsonResponse
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

            // Find member
            $member = Member::findOrFail($request->id);

            // Check if already verified
            if ($member->hasVerifiedEmail()) {
                return $this->successResponse(
                    ['verified' => true],
                    'Email already verified',
                    200
                );
            }

            // Verify hash matches email
            if (!hash_equals(
                (string) $request->hash,
                sha1($member->getEmailForVerification())
            )) {
                return $this->errorResponse(
                    'Invalid verification link',
                    403
                );
            }

            // Check if link expired
            if ($request->expires < now()->timestamp) {
                return $this->errorResponse(
                    'Verification link has expired. Please request a new one.',
                    410 // Gone
                );
            }

            // Verify signature
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
                return $this->errorResponse(
                    'Invalid verification signature',
                    403
                );
            }

            // Mark email as verified
            $member->markEmailAsVerified();

            // Fire verified event
            event(new Verified($member));

            Log::info('Email verified successfully', [
                'member_id' => $member->id,
                'email' => $member->email,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([
                'verified' => true,
                'verified_at' => $member->email_verified_at->toISOString(),
                'member' => [
                    'id' => $member->id,
                    'email' => $member->email,
                    'name' => $member->name,
                ],
            ], 'Email verified successfully', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Member not found', 404);
        } catch (\Exception $e) {
            Log::error('Email verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Verification failed. Please try again.',
                500
            );
        }
    }

    /**
     * Resend verification email
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resend(Request $request): JsonResponse
    {
        try {
            // Rate limiting
            $rateLimitKey = 'verify-email-resend:' . $request->user()->id;
            if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_RESEND)) {
                return $this->errorResponse(
                    'Too many requests. Please try again in ' .
                        ceil(RateLimiter::availableIn($rateLimitKey) / 60) . ' minutes.',
                    429
                );
            }

            $member = $request->user();

            // Check if already verified
            if ($member->hasVerifiedEmail()) {
                return $this->errorResponse(
                    'Email is already verified',
                    400
                );
            }

            // Send verification email
            $this->sendVerificationEmail($member);

            // Hit rate limiter
            RateLimiter::hit($rateLimitKey, 60); // 1 minute

            Log::info('Verification email resent', [
                'member_id' => $member->id,
                'email' => $member->email,
            ]);

            return $this->successResponse([
                'sent' => true,
                'email' => $member->email,
            ], 'Verification email sent successfully', 200);
        } catch (\Exception $e) {
            Log::error('Resend verification error', [
                'member_id' => $request->user()->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to send verification email. Please try again.',
                500
            );
        }
    }

    /**
     * Check verification status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        $member = $request->user();

        return $this->successResponse([
            'verified' => $member->hasVerifiedEmail(),
            'email' => $member->email,
            'verified_at' => $member->email_verified_at?->toISOString(),
        ], 'Email verification status retrieved', 200);
    }

    /**
     * Send verification email
     *
     * @param Member $member
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
                'expiresIn' => self::VERIFICATION_LINK_EXPIRES / 60, // hours
            ],
            memberId: $member->id,
            metadata: [
                'verification_url' => $verificationUrl,
                'expires_at' => now()->addMinutes(self::VERIFICATION_LINK_EXPIRES),
            ]
        );
    }

    /**
     * Generate verification URL
     *
     * @param Member $member
     * @return string
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

    /**
     * Success response helper
     */
    private function successResponse(array $data, string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Error response helper
     */
    private function errorResponse(string $message, int $code = 400, ?array $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
