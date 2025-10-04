<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\StorySubmissionRequest;
use App\Models\MemberStorySubmission;
use App\Models\SubmissionSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Member Story Submission Controller
 * 
 * Handles story submissions from members in the Flutter app.
 */
class MemberStorySubmissionController extends Controller
{
    /**
     * Get submission settings (guide text and terms & conditions)
     * 
     * GET /v1/submissions/settings
     * Rate Limited: 20 requests per minute
     */
    public function getSettings(): JsonResponse
    {
        try {
            $guideText = SubmissionSetting::getGuideText();
            $termsText = SubmissionSetting::getTermsText();

            return response()->json([
                'success' => true,
                'data' => [
                    'guide_text' => $guideText,
                    'terms_text' => $termsText,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch submission settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل تحميل الإعدادات',
            ], 500);
        }
    }

    /**
     * Submit a new story
     * 
     * POST /v1/submissions/submit
     * Rate Limited: 5 requests per minute (prevent spam)
     */
    public function submit(StorySubmissionRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\Member $member */
            $member = Auth::guard('sanctum')->user();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'يجب تسجيل الدخول أولاً',
                ], 401);
            }

            // Additional rate limiting per member (max 5 submissions per hour)
            $rateLimitKey = 'story_submission:member_' . $member->id;

            if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                $minutes = ceil($seconds / 60);

                return response()->json([
                    'success' => false,
                    'message' => "لقد وصلت للحد الأقصى من الإرسالات. يرجى المحاولة بعد {$minutes} دقيقة",
                ], 429);
            }

            $validated = $request->validated();

            // Create submission
            DB::beginTransaction();

            $submission = MemberStorySubmission::create([
                'member_id' => $member->id,
                'story_title' => $validated['story_title'],
                'story_content' => $validated['story_content'],
                'category_id' => $validated['category_id'],
                'submission_status' => 'pending',
                'submitted_at' => now(),
            ]);

            DB::commit();

            // Hit rate limiter
            RateLimiter::hit($rateLimitKey, 3600); // 1 hour decay

            // Log the submission
            Log::info('New story submission received', [
                'submission_id' => $submission->id,
                'member_id' => $member->id,
                'category_id' => $validated['category_id'],
                'title_length' => strlen($validated['story_title']),
                'content_length' => strlen($validated['story_content']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال قصتك بنجاح! سيتم مراجعتها قريباً',
                'data' => [
                    'submission_id' => $submission->id,
                    'submitted_at' => $submission->submitted_at->toISOString(),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Story submission failed', [
                'member_id' => $member->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل إرسال القصة. يرجى المحاولة لاحقاً',
            ], 500);
        }
    }

    /**
     * Get member's submission history (FUTURE FEATURE - NOT IMPLEMENTED YET)
     * 
     * GET /v1/submissions/my-submissions
     * Rate Limited: 20 requests per minute
     */
    public function mySubmissions(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'هذه الميزة غير متاحة حالياً',
            'data' => [],
        ], 501); // 501 Not Implemented
    }
}
