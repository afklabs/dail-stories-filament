<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FAQ;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FAQ API Controller
 * 
 * Provides FAQ data to mobile app members.
 * Public endpoint - no authentication required.
 */
class FAQController extends Controller
{
    /**
     * Get all active FAQs in display order
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // Cache FAQs for 1 hour (since they don't change frequently)
            $faqs = Cache::remember('faqs_active', 3600, function () {
                return FAQ::getActiveFAQs();
            });

            return response()->json([
                'success' => true,
                'message' => 'FAQs retrieved successfully',
                'data' => [
                    'faqs' => $faqs,
                    'count' => $faqs->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('FAQ retrieval error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve FAQs',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a specific FAQ by ID
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $faq = FAQ::active()->find($id);

            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found or inactive',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'FAQ retrieved successfully',
                'data' => [
                    'faq' => $faq,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('FAQ show error', [
                'faq_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve FAQ',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
