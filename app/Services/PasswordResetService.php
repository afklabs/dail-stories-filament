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
 * Handles password reset functionality for members.
 * This is a placeholder implementation since mail is not configured yet.
 * 
 * TODO: Implement actual email sending when mail configuration is complete.
 * 
 * @author Development Team
 * @version 1.0.0
 */
class PasswordResetService
{
    /**
     * Send password reset email (placeholder)
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

            // TODO: Send email when mail configuration is ready
            // Example implementation:
            /*
            Mail::to($member->email)->send(new PasswordResetMail([
                'member' => $member,
                'token' => $token,
                'reset_url' => config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($member->email),
                'expires_at' => now()->addHours(2),
            ]));
            */

            Log::info('Password reset token generated', [
                'member_id' => $member->id,
                'email' => $member->email,
                'token_length' => strlen($token),
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
            $this->deleteResetToken($email);
            return false;
        }

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
        try {
            if (!$this->verifyResetToken($email, $token)) {
                return false;
            }

            $member = Member::where('email', $email)->first();
            if (!$member) {
                return false;
            }

            DB::transaction(function () use ($member, $newPassword, $email) {
                // Update password
                $member->update([
                    'password' => Hash::make($newPassword),
                    'password_changed_at' => now(),
                ]);

                // Revoke all existing tokens for security
                $member->tokens()->delete();

                // Delete reset token
                $this->deleteResetToken($email);
            });

            Log::info('Password reset completed', [
                'member_id' => $member->id,
                'email' => $email,
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
     * Delete reset token
     * 
     * @param string $email
     * @return void
     */
    public function deleteResetToken(string $email): void
    {
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();
    }

    /**
     * Cleanup expired reset tokens
     * 
     * This method should be called periodically (e.g., via scheduled job)
     * 
     * @return int Number of deleted tokens
     */
    public function cleanupExpiredTokens(): int
    {
        return DB::table('password_reset_tokens')
            ->where('created_at', '<', now()->subHours(2))
            ->delete();
    }
}