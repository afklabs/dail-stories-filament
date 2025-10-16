<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Password Reset Service
 * 
 * Handles password reset functionality for members with email integration
 */
class PasswordResetService
{
    public function __construct(
        private EmailService $emailService
    ) {}

    /**
     * Send password reset email
     * 
     * @param Member $member
     * @return bool
     */
    public function sendResetEmail(Member $member): bool
    {
        try {
            // Generate secure reset token
            $token = Str::random(60);

            // Store token in database
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $member->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            // Send email using EmailService
            $this->emailService->sendPasswordResetEmail($member, $token);

            Log::info('Password reset email sent successfully', [
                'member_id' => $member->id,
                'email' => $member->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'member_id' => $member->id,
                'email' => $member->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify reset token
     * 
     * @param string $email
     * @param string $token
     * @return bool
     */
    public function verifyResetToken(string $email, string $token): bool
    {
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$resetRecord) {
            return false;
        }

        // Check if token has expired (2 hours)
        if (now()->diffInHours($resetRecord->created_at) > 2) {
            // Delete expired token
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            return false;
        }

        // Verify token
        return Hash::check($token, $resetRecord->token);
    }

    /**
     * Reset password using token
     * 
     * @param string $email
     * @param string $token
     * @param string $newPassword
     * @return bool
     */
    public function resetPassword(string $email, string $token, string $newPassword): bool
    {
        // Verify token first
        if (!$this->verifyResetToken($email, $token)) {
            return false;
        }

        try {
            // Find member
            $member = Member::where('email', $email)->first();

            if (!$member) {
                return false;
            }

            // Update password
            $member->update([
                'password' => Hash::make($newPassword),
                'password_changed_at' => now(),
            ]);

            // Delete used token
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            // Revoke all existing tokens for security
            $member->tokens()->delete();

            Log::info('Password reset successful', [
                'member_id' => $member->id,
                'email' => $member->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Password reset failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete expired tokens (cleanup job)
     */
    public function deleteExpiredTokens(): int
    {
        return DB::table('password_reset_tokens')
            ->where('created_at', '<', now()->subHours(2))
            ->delete();
    }
}
