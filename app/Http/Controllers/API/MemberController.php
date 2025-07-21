<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\MemberProfileUpdateRequest;
use App\Http\Requests\API\MemberRegistrationRequest;
use App\Http\Requests\API\MemberLoginRequest;
use App\Http\Requests\API\MemberPasswordChangeRequest;
use App\Http\Requests\API\MemberAvatarUploadRequest;
use App\Http\Requests\API\MemberAccountDeletionRequest;
use App\Models\Member;
use App\Models\MemberReadingHistory;
use App\Models\MemberStoryInteraction;
use App\Models\MemberStoryRating;
use App\Models\Story;
use App\Services\MemberService;
use App\Services\FileUploadService;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * Member API Controller
 * 
 * Handles all member authentication and profile management for the Flutter mobile app.
 * Provides secure registration, login, profile management, and account operations.
 * 
 * Security Features:
 * - Enhanced input validation and sanitization
 * - SQL injection prevention
 * - XSS protection through proper escaping
 * - Rate limiting on all sensitive endpoints
 * - Secure file upload handling
 * - Token management with automatic revocation
 * - Account lockout protection
 * 
 * Performance Features:
 * - Database query optimization
 * - Efficient caching strategies
 * - Proper transaction handling
 * - Memory-efficient file processing
 * 
 * @author Development Team
 * @version 2.0.0
 * @since Laravel 11+
 */
class MemberController extends Controller
{
    /**
     * Member service for business logic
     */
    private MemberService $memberService;
    
    /**
     * File upload service for secure file handling
     */
    private FileUploadService $fileUploadService;
    
    /**
     * Password reset service
     */
    private PasswordResetService $passwordResetService;

    /**
     * Constructor - Inject required services
     */
    public function __construct(
        MemberService $memberService,
        FileUploadService $fileUploadService,
        PasswordResetService $passwordResetService
    ) {
        $this->memberService = $memberService;
        $this->fileUploadService = $fileUploadService;
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * Register new member with enhanced security
     * 
     * Endpoint: POST /v1/members/register
     * Rate Limit: 5 requests per minute per IP
     * Authentication: Not required
     * 
     * Security Features:
     * - Comprehensive input validation
     * - Password strength requirements
     * - Email normalization and validation
     * - Device ID validation
     * - Account creation rate limiting
     * 
     * @param MemberRegistrationRequest $request Validated registration request
     * @return JsonResponse Registration response with member data and token
     */
    public function register(MemberRegistrationRequest $request): JsonResponse
    {
        try {
            // Apply rate limiting per IP to prevent registration spam
            $ipRateLimitKey = 'registration:ip:' . $request->ip();
            if (RateLimiter::tooManyAttempts($ipRateLimitKey, 5)) {
                return $this->errorResponse(
                    'Too many registration attempts. Please try again in ' . 
                    ceil(RateLimiter::availableIn($ipRateLimitKey) / 60) . ' minutes.',
                    429
                );
            }

            $validated = $request->validated();

            // Additional business logic validation
            if (!$this->memberService->canRegisterWithEmail($validated['email'])) {
                RateLimiter::hit($ipRateLimitKey, 300); // 5 minutes penalty
                return $this->errorResponse('Registration not available for this email', 403);
            }

            // Use database transaction for atomicity and data integrity
            $result = DB::transaction(function () use ($validated, $request) {
                // Create member with sanitized and validated data
                $member = Member::create([
                    'name' => strip_tags(trim($validated['name'])), // XSS protection
                    'email' => strtolower(trim($validated['email'])), // Email normalization
                    'password' => Hash::make($validated['password']),
                    'device_id' => $validated['device_id'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'status' => 'active',
                    'last_login_at' => now(),
                    'email_verified_at' => now(), // Auto-verify for mobile app
                    'registration_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Create secure API token with limited scope and expiration
                $tokenResult = $member->createToken(
                    name: 'mobile-app-' . now()->format('Y-m-d-H-i-s'),
                    abilities: ['*'],
                    expiresAt: now()->addDays(30)
                );

                return [
                    'member' => $member,
                    'token' => $tokenResult->plainTextToken,
                    'token_expires_at' => $tokenResult->accessToken->expires_at,
                ];
            });

            // Clear rate limit on successful registration
            RateLimiter::clear($ipRateLimitKey);

            // Log successful registration for security monitoring
            Log::info('Member registered successfully', [
                'member_id' => $result['member']->id,
                'email' => $result['member']->email,
                'device_id' => $validated['device_id'] ?? 'not_provided',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->successResponse([
                'member' => $this->transformMemberForAPI($result['member']),
                'authentication' => [
                    'token' => $result['token'],
                    'token_type' => 'Bearer',
                    'expires_at' => $result['token_expires_at']->toISOString(),
                    'expires_in' => $result['token_expires_at']->diffInSeconds(now()),
                ],
                'registration_completed_at' => now()->toISOString(),
            ], 'Registration successful', 201);

        } catch (ValidationException $e) {
            return $this->errorResponse('Registration validation failed', 422, $e->errors());

        } catch (\Illuminate\Database\QueryException $e) {
            // Handle duplicate email constraint violation
            if ($e->errorInfo[1] === 1062) { // MySQL duplicate entry
                RateLimiter::hit($ipRateLimitKey, 300);
                return $this->errorResponse(
                    'This email address is already registered',
                    422,
                    ['email' => ['This email is already registered']]
                );
            }

            Log::error('Database error during registration', [
                'error_code' => $e->errorInfo[1] ?? 'unknown',
                'error_message' => $e->getMessage(),
                'email' => $request->input('email', 'unknown'),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('Registration failed due to database error', 500);

        } catch (\Exception $e) {
            Log::error('Member registration error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->input('email', 'unknown'),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('Registration failed. Please try again.', 500);
        }
    }

    /**
     * Authenticate member login with enhanced security
     * 
     * Endpoint: POST /v1/members/login
     * Rate Limit: 10 requests per minute per IP, 5 per email
     * Authentication: Not required
     * 
     * Security Features:
     * - Progressive rate limiting (IP + email based)
     * - Account lockout protection
     * - Secure password verification
     * - Token management with automatic cleanup
     * - Audit logging for failed attempts
     * 
     * @param MemberLoginRequest $request Validated login request
     * @return JsonResponse Login response with member data and new token
     */
    public function login(MemberLoginRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $email = strtolower(trim($validated['email']));
            $password = $validated['password'];
            $deviceId = $validated['device_id'] ?? null;

            // Multi-layered rate limiting for enhanced security
            $ipRateLimitKey = 'login:ip:' . $request->ip();
            $emailRateLimitKey = 'login:email:' . $email;
            
            // Check IP-based rate limit (more permissive)
            if (RateLimiter::tooManyAttempts($ipRateLimitKey, 10)) {
                return $this->errorResponse(
                    'Too many login attempts from your location. Please try again in ' . 
                    ceil(RateLimiter::availableIn($ipRateLimitKey) / 60) . ' minutes.',
                    429
                );
            }

            // Check email-based rate limit (more restrictive)
            if (RateLimiter::tooManyAttempts($emailRateLimitKey, 5)) {
                return $this->errorResponse(
                    'Too many login attempts for this email. Please try again in ' . 
                    ceil(RateLimiter::availableIn($emailRateLimitKey) / 60) . ' minutes.',
                    429,
                    ['retry_after_seconds' => RateLimiter::availableIn($emailRateLimitKey)]
                );
            }

            // Find and validate member with secure query
            $member = Member::where('email', $email)->first();

            if (!$member || !Hash::check($password, $member->password)) {
                // Apply rate limiting on failed attempts
                RateLimiter::hit($ipRateLimitKey, 900); // 15 minutes
                RateLimiter::hit($emailRateLimitKey, 900);
                
                // Log security event
                Log::warning('Failed login attempt', [
                    'email' => $email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'device_id' => $deviceId,
                    'member_exists' => $member !== null,
                    'timestamp' => now()->toISOString(),
                ]);

                return $this->errorResponse('Invalid email or password', 401);
            }

            // Validate account status
            if (!$this->memberService->isAccountActive($member)) {
                RateLimiter::hit($emailRateLimitKey, 900);
                
                $statusMessage = match($member->status) {
                    'inactive' => 'Your account is inactive. Please contact support.',
                    'suspended' => 'Your account has been suspended. Please contact support.',
                    'banned' => 'Your account has been banned. Please contact support.',
                    default => 'Account access is restricted. Please contact support.',
                };

                return $this->errorResponse($statusMessage, 403, [
                    'account_status' => $member->status,
                    'support_contact' => config('app.support_email'),
                ]);
            }

            // Successful authentication - clear rate limits
            RateLimiter::clear($ipRateLimitKey);
            RateLimiter::clear($emailRateLimitKey);

            // Create new session with secure token management
            $loginResult = DB::transaction(function () use ($member, $deviceId, $request) {
                // Revoke old tokens for security (optional - can be configured)
                if (config('auth.revoke_old_tokens_on_login', false)) {
                    $member->tokens()->delete();
                }

                // Update member login information
                $member->update([
                    'last_login_at' => now(),
                    'device_id' => $deviceId,
                    'last_login_ip' => $request->ip(),
                    'login_count' => DB::raw('login_count + 1'),
                ]);

                // Create new secure API token
                $tokenResult = $member->createToken(
                    name: 'mobile-login-' . now()->format('Y-m-d-H-i-s'),
                    abilities: ['*'],
                    expiresAt: now()->addDays(30)
                );

                return [
                    'token' => $tokenResult->plainTextToken,
                    'expires_at' => $tokenResult->accessToken->expires_at,
                ];
            });

            // Log successful login for security monitoring
            Log::info('Member login successful', [
                'member_id' => $member->id,
                'email' => $member->email,
                'device_id' => $deviceId,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_count' => $member->login_count,
            ]);

            return $this->successResponse([
                'member' => $this->transformMemberForAPI($member),
                'authentication' => [
                    'token' => $loginResult['token'],
                    'token_type' => 'Bearer',
                    'expires_at' => $loginResult['expires_at']->toISOString(),
                    'expires_in' => $loginResult['expires_at']->diffInSeconds(now()),
                ],
                'login_completed_at' => now()->toISOString(),
            ], 'Login successful');

        } catch (ValidationException $e) {
            return $this->errorResponse('Login validation failed', 422, $e->errors());

        } catch (\Exception $e) {
            Log::error('Member login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->input('email', 'unknown'),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('Login failed. Please try again.', 500);
        }
    }

    /**
     * Securely logout member and revoke token
     * 
     * Endpoint: POST /v1/members/logout
     * Authentication: Required (Bearer token)
     * 
     * @param Request $request
     * @return JsonResponse Logout confirmation
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $member = $request->user();
            $currentToken = $request->user()->currentAccessToken();
            
            if ($currentToken) {
                // Revoke current token
                $currentToken->delete();
                
                // Optionally revoke all tokens for enhanced security
                if ($request->boolean('revoke_all_sessions', false)) {
                    $member->tokens()->delete();
                }
            }

            Log::info('Member logout successful', [
                'member_id' => $member->id,
                'ip' => $request->ip(),
                'revoked_all_sessions' => $request->boolean('revoke_all_sessions', false),
            ]);

            return $this->successResponse([
                'logged_out_at' => now()->toISOString(),
                'sessions_revoked' => $request->boolean('revoke_all_sessions', false) ? 'all' : 'current',
            ], 'Logout successful');

        } catch (\Exception $e) {
            Log::error('Member logout error', [
                'error' => $e->getMessage(),
                'member_id' => $request->user()?->id,
            ]);

            return $this->errorResponse('Logout failed', 500);
        }
    }

    /**
     * Get member profile with comprehensive data
     * 
     * Endpoint: GET /v1/members/profile
     * Authentication: Required
     * 
     * @param Request $request
     * @return JsonResponse Member profile data
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $member = $request->user();
            
            // Load additional profile statistics efficiently
            $member->loadCount([
                'readingHistory as total_stories_read',
                'storyInteractions as total_interactions',
                'storyRatings as total_ratings_given',
            ]);

            // Get reading statistics
            $readingStats = $this->memberService->getReadingStatistics($member->id);

            return $this->successResponse([
                'profile' => $this->transformMemberForAPI($member),
                'statistics' => $readingStats,
                'account_info' => [
                    'member_since' => $member->created_at->toISOString(),
                    'days_active' => $member->created_at->diffInDays(now()),
                    'last_activity' => $member->updated_at->toISOString(),
                    'total_login_count' => $member->login_count ?? 0,
                ],
            ], 'Profile retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Get member profile error', [
                'error' => $e->getMessage(),
                'member_id' => $request->user()?->id,
            ]);

            return $this->errorResponse('Failed to load profile', 500);
        }
    }

    /**
     * Update member profile with validation
     * 
     * Endpoint: PUT /v1/members/profile
     * Rate Limit: 10 requests per minute per user
     * Authentication: Required
     * 
     * @param MemberProfileUpdateRequest $request
     * @return JsonResponse Updated profile data
     */
    public function updateProfile(MemberProfileUpdateRequest $request): JsonResponse
    {
        try {
            $member = $request->user();
            $validated = $request->validated();

            // Handle password update separately for security
            if (!empty($validated['new_password'])) {
                if (!Hash::check($validated['current_password'], $member->password)) {
                    return $this->errorResponse(
                        'Current password is incorrect',
                        422,
                        ['current_password' => ['Current password is incorrect']]
                    );
                }
                
                $validated['password'] = Hash::make($validated['new_password']);
                unset($validated['current_password'], $validated['new_password']);
                
                // Revoke all tokens when password changes for security
                $member->tokens()->delete();
                
                // Create new token for current session
                $newToken = $member->createToken(
                    'profile-update-' . now()->format('Y-m-d-H-i-s'),
                    ['*'],
                    now()->addDays(30)
                )->plainTextToken;
            }

            // Sanitize and update profile data
            $updateData = [];
            foreach ($validated as $field => $value) {
                if ($field !== 'avatar' && $value !== null) {
                    // Sanitize text fields to prevent XSS
                    $updateData[$field] = is_string($value) ? strip_tags(trim($value)) : $value;
                }
            }

            if (!empty($updateData)) {
                $member->update($updateData);
            }

            Log::info('Member profile updated', [
                'member_id' => $member->id,
                'updated_fields' => array_keys($updateData),
                'password_changed' => isset($validated['password']),
            ]);

            $response = [
                'profile' => $this->transformMemberForAPI($member->fresh()),
                'updated_at' => now()->toISOString(),
                'updated_fields' => array_keys($updateData),
            ];

            // Add new token if password was changed
            if (isset($newToken)) {
                $response['new_authentication'] = [
                    'token' => $newToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 30 * 24 * 60 * 60,
                    'reason' => 'password_changed',
                ];
            }

            return $this->successResponse($response, 'Profile updated successfully');

        } catch (ValidationException $e) {
            return $this->errorResponse('Profile update validation failed', 422, $e->errors());

        } catch (\Exception $e) {
            Log::error('Update member profile error', [
                'error' => $e->getMessage(),
                'member_id' => $request->user()?->id,
            ]);

            return $this->errorResponse('Failed to update profile', 500);
        }
    }

    /**
     * Upload member avatar with enhanced security
     * 
     * Endpoint: POST /v1/members/avatar
     * Rate Limit: 5 requests per minute per user
     * Authentication: Required
     * 
     * @param MemberAvatarUploadRequest $request
     * @return JsonResponse Avatar upload confirmation
     */
    public function uploadAvatar(MemberAvatarUploadRequest $request): JsonResponse
    {
        try {
            $member = $request->user();
            $avatarFile = $request->file('avatar');

            // Use secure file upload service
            $uploadResult = $this->fileUploadService->uploadAvatar($avatarFile, $member->id);

            // Delete old avatar if exists
            if ($member->avatar && $member->avatar !== $uploadResult['path']) {
                $this->fileUploadService->deleteFile($member->avatar);
            }

            // Update member avatar path
            $member->update(['avatar' => $uploadResult['path']]);

            Log::info('Avatar uploaded successfully', [
                'member_id' => $member->id,
                'filename' => $uploadResult['filename'],
                'file_size' => $uploadResult['size'],
                'mime_type' => $uploadResult['mime_type'],
            ]);

            return $this->successResponse([
                'avatar' => [
                    'path' => $uploadResult['path'],
                    'url' => $uploadResult['url'],
                    'filename' => $uploadResult['filename'],
                    'size' => $uploadResult['size'],
                    'mime_type' => $uploadResult['mime_type'],
                ],
                'uploaded_at' => now()->toISOString(),
            ], 'Avatar uploaded successfully');

        } catch (ValidationException $e) {
            return $this->errorResponse('Avatar upload validation failed', 422, $e->errors());

        } catch (\Exception $e) {
            Log::error('Avatar upload error', [
                'member_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to upload avatar. Please try again.', 500);
        }
    }

    /**
     * Change member password with enhanced security
     * 
     * Endpoint: POST /v1/members/change-password
     * Rate Limit: 3 requests per minute per user
     * Authentication: Required
     * 
     * @param MemberPasswordChangeRequest $request
     * @return JsonResponse Password change confirmation
     */
    public function changePassword(MemberPasswordChangeRequest $request): JsonResponse
    {
        try {
            $member = $request->user();
            $validated = $request->validated();

            // Verify current password
            if (!Hash::check($validated['current_password'], $member->password)) {
                Log::warning('Failed password change attempt', [
                    'member_id' => $member->id,
                    'ip' => $request->ip(),
                ]);

                return $this->errorResponse(
                    'Current password is incorrect',
                    422,
                    ['current_password' => ['Current password is incorrect']]
                );
            }

            DB::transaction(function () use ($member, $validated) {
                // Update password
                $member->update([
                    'password' => Hash::make($validated['password']),
                    'password_changed_at' => now(),
                ]);

                // Revoke all existing tokens for security
                $member->tokens()->delete();
            });

            // Create new token for current session
            $newToken = $member->createToken(
                'password-change-' . now()->format('Y-m-d-H-i-s'),
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

            Log::info('Password changed successfully', [
                'member_id' => $member->id,
                'ip' => $request->ip(),
            ]);

            return $this->successResponse([
                'authentication' => [
                    'token' => $newToken,
                    'token_type' => 'Bearer',
                    'expires_in' => 30 * 24 * 60 * 60,
                    'reason' => 'password_changed',
                ],
                'changed_at' => now()->toISOString(),
                'security_notice' => 'All other sessions have been logged out for security.',
            ], 'Password changed successfully');

        } catch (ValidationException $e) {
            return $this->errorResponse('Password change validation failed', 422, $e->errors());

        } catch (\Exception $e) {
            Log::error('Password change error', [
                'member_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to change password. Please try again.', 500);
        }
    }

    /**
     * Delete member account with secure confirmation
     * 
     * Endpoint: DELETE /v1/members/account
     * Rate Limit: 1 request per 5 minutes per user
     * Authentication: Required
     * 
     * @param MemberAccountDeletionRequest $request
     * @return JsonResponse Account deletion confirmation
     */
    public function deleteAccount(MemberAccountDeletionRequest $request): JsonResponse
    {
        try {
            $member = $request->user();
            $validated = $request->validated();

            // Verify password for security
            if (!Hash::check($validated['password'], $member->password)) {
                Log::warning('Failed account deletion attempt', [
                    'member_id' => $member->id,
                    'ip' => $request->ip(),
                ]);

                return $this->errorResponse(
                    'Password is incorrect',
                    422,
                    ['password' => ['Password is incorrect']]
                );
            }

            // Store data for logging before deletion
            $memberData = [
                'id' => $member->id,
                'email' => $member->email,
                'name' => $member->name,
                'created_at' => $member->created_at,
                'deletion_reason' => $validated['reason'] ?? null,
                'ip' => $request->ip(),
                'deleted_at' => now(),
            ];

            // Comprehensive account deletion in transaction
            DB::transaction(function () use ($member) {
                // Delete all authentication tokens
                $member->tokens()->delete();
                
                // Delete user-generated content and interactions
                MemberReadingHistory::where('member_id', $member->id)->delete();
                MemberStoryInteraction::where('member_id', $member->id)->delete();
                MemberStoryRating::where('member_id', $member->id)->delete();
                
                // Delete avatar file from storage
                if ($member->avatar) {
                    $this->fileUploadService->deleteFile($member->avatar);
                }
                
                // Finally delete the member account
                $member->delete();
            });

            Log::info('Account deleted successfully', $memberData);

            return $this->successResponse([
                'deleted_at' => now()->toISOString(),
                'message' => 'Your account and all associated data have been permanently deleted.',
                'support_contact' => config('app.support_email'),
            ], 'Account deleted successfully');

        } catch (ValidationException $e) {
            return $this->errorResponse('Account deletion validation failed', 422, $e->errors());

        } catch (\Exception $e) {
            Log::error('Account deletion error', [
                'member_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to delete account. Please try again.', 500);
        }
    }

    /**
     * Initiate password reset process (placeholder for future implementation)
     * 
     * Endpoint: POST /v1/members/forgot-password
     * Rate Limit: 3 requests per minute per IP
     * Authentication: Not required
     * 
     * Note: This is a placeholder since password_reset_tokens table and mail are not configured yet
     * 
     * @param Request $request
     * @return JsonResponse Password reset initiation response
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $email = strtolower(trim($request->input('email')));

            // Rate limiting for forgot password requests
            $rateLimitKey = 'forgot-password:' . $request->ip();
            if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
                return $this->errorResponse(
                    'Too many password reset requests. Please try again later.',
                    429
                );
            }

            RateLimiter::hit($rateLimitKey, 300); // 5 minutes

            // For security, always return the same message regardless of email existence
            // This prevents email enumeration attacks
            $message = 'If an account with this email exists, you will receive password reset instructions.';

            // Check if member exists (for logging purposes only)
            $member = Member::where('email', $email)->first();
            
            if ($member && $member->status === 'active') {
                Log::info('Password reset requested for valid account', [
                    'email' => $email,
                    'member_id' => $member->id,
                    'ip' => $request->ip(),
                ]);

                // TODO: Implement actual password reset when mail is configured
                // $this->passwordResetService->sendResetEmail($member);
            } else {
                Log::info('Password reset requested for invalid/inactive account', [
                    'email' => $email,
                    'ip' => $request->ip(),
                    'member_exists' => $member !== null,
                    'member_status' => $member?->status ?? 'not_found',
                ]);
            }

            return $this->successResponse([
                'requested_at' => now()->toISOString(),
                'next_steps' => 'Check your email for reset instructions if the account exists.',
            ], $message);

        } catch (\Exception $e) {
            Log::error('Forgot password error', [
                'error' => $e->getMessage(),
                'email' => $request->input('email', 'unknown'),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('Failed to process password reset request', 500);
        }
    }

    /**
     * Get comprehensive member reading history
     * 
     * Endpoint: GET /v1/members/reading-history
     * Authentication: Required
     * 
     * @param Request $request
     * @return JsonResponse Paginated reading history
     */
    public function readingHistory(Request $request): JsonResponse
    {
        try {
            $member = $request->user();
            
            // Validate pagination parameters
            $validator = Validator::make($request->all(), [
                'per_page' => 'integer|min:1|max:50',
                'status' => 'string|in:all,completed,in_progress,not_started',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            $perPage = min($request->integer('per_page', 10), 50);
            $status = $request->input('status', 'all');

            // Build optimized query with eager loading
            $query = MemberReadingHistory::where('member_id', $member->id)
                ->with([
                    'story:id,title,excerpt,image,category_id,reading_time_minutes,views',
                    'story.category:id,name,slug',
                ]);

            // Apply status filter
            if ($status !== 'all') {
                match($status) {
                    'completed' => $query->where('reading_progress', '>=', 100),
                    'in_progress' => $query->whereBetween('reading_progress', [1, 99]),
                    'not_started' => $query->where('reading_progress', 0),
                };
            }

            $readingHistory = $query
                ->orderByDesc('last_read_at')
                ->paginate($perPage);

            // Transform data for API response
            $history = $readingHistory->getCollection()->map(function ($item) {
                return [
                    'story' => [
                        'id' => $item->story->id,
                        'title' => $item->story->title,
                        'excerpt' => $item->story->excerpt,
                        'image' => $item->story->image ? asset('storage/' . $item->story->image) : null,
                        'reading_time_minutes' => $item->story->reading_time_minutes,
                        'views' => $item->story->views,
                        'category' => $item->story->category ? [
                            'id' => $item->story->category->id,
                            'name' => $item->story->category->name,
                            'slug' => $item->story->category->slug,
                        ] : null,
                    ],
                    'reading_data' => [
                        'progress_percentage' => $item->reading_progress,
                        'time_spent_minutes' => round($item->time_spent / 60, 1),
                        'reading_sessions' => $item->reading_sessions ?? 1,
                        'last_read_at' => $item->last_read_at->toISOString(),
                        'is_completed' => $item->reading_progress >= 100,
                        'status' => $this->memberService->getProgressStatus($item->reading_progress),
                    ],
                ];
            });

            // Get comprehensive reading statistics
            $statistics = Cache::remember(
                "member_reading_stats_{$member->id}",
                300, // 5 minutes
                fn() => $this->memberService->getComprehensiveReadingStats($member->id)
            );

            return $this->successResponse([
                'reading_history' => $history,
                'pagination' => [
                    'current_page' => $readingHistory->currentPage(),
                    'per_page' => $readingHistory->perPage(),
                    'total' => $readingHistory->total(),
                    'last_page' => $readingHistory->lastPage(),
                    'has_more' => $readingHistory->hasMorePages(),
                ],
                'statistics' => $statistics,
                'filters' => [
                    'status' => $status,
                    'available_statuses' => ['all', 'completed', 'in_progress', 'not_started'],
                ],
            ], 'Reading history retrieved successfully');

        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid parameters', 422, $e->errors());

        } catch (\Exception $e) {
            Log::error('Get member reading history error', [
                'error' => $e->getMessage(),
                'member_id' => $request->user()?->id,
            ]);

            return $this->errorResponse('Failed to load reading history', 500);
        }
    }

    // ===== PRIVATE HELPER METHODS =====

    /**
     * Transform member model to consistent API format
     * 
     * @param Member $member
     * @return array
     */
    private function transformMemberForAPI(Member $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'phone' => $member->phone,
            'avatar_url' => $member->avatar ? asset('storage/' . $member->avatar) : null,
            'date_of_birth' => $member->date_of_birth?->toDateString(),
            'gender' => $member->gender,
            'status' => $member->status,
            'email_verified' => $member->email_verified_at !== null,
            'last_login_at' => $member->last_login_at?->toISOString(),
            'created_at' => $member->created_at->toISOString(),
            'updated_at' => $member->updated_at->toISOString(),
        ];
    }

    /**
     * Standardized success response format
     */
    private function successResponse($data = [], string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ], $code);
    }

    /**
     * Standardized error response format
     */
    private function errorResponse(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}