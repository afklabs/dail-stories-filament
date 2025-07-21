<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

abstract class BaseAdminController extends Controller
{
    /**
     * Standard error response format
     */
    protected function errorResponse(string $message, int $status = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ], $status);
    }

    /**
     * Standard success response format
     */
    protected function successResponse(array $data, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Check if user has required permissions
     */
    protected function checkPermission(string $permission): bool
    {
        return auth()->user()?->can($permission) ?? false;
    }

    /**
     * Rate limiting check for heavy operations
     */
    protected function checkRateLimit(string $key, int $maxAttempts = 10, int $decayMinutes = 1): bool
    {
        $key = "rate_limit:{$key}:".auth()->id();

        if (Cache::get($key, 0) >= $maxAttempts) {
            return false;
        }

        Cache::put($key, Cache::get($key, 0) + 1, now()->addMinutes($decayMinutes));

        return true;
    }
}
