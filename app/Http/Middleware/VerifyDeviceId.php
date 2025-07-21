<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify device ID for mobile API security
 */
class VerifyDeviceId
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip verification for certain routes (like registration)
        $skipRoutes = [
            'api.v1.members.register',
            'api.v1.members.login',
        ];

        if (in_array($request->route()?->getName(), $skipRoutes)) {
            return $next($request);
        }

        // Get device ID from header or request
        $deviceId = $request->header('X-Device-ID') ?? $request->input('device_id');

        if (!$deviceId) {
            return response()->json([
                'success' => false,
                'message' => 'Device ID required',
                'error' => 'Missing Device ID',
            ], 400);
        }

        // Validate device ID format (customize based on your requirements)
        if (!$this->isValidDeviceId($deviceId)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid device ID format',
                'error' => 'Invalid Device ID',
            ], 400);
        }

        // If user is authenticated, verify device ID matches
        if ($request->user() && $request->user() instanceof \App\Models\Member) {
            $member = $request->user();
            
            // Allow if device ID matches or if member doesn't have device ID set
            if ($member->device_id && $member->device_id !== $deviceId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device verification failed',
                    'error' => 'Device Mismatch',
                ], 403);
            }
        }

        // Add device ID to request for controllers to use
        $request->merge(['verified_device_id' => $deviceId]);

        return $next($request);
    }

    /**
     * Validate device ID format
     */
    private function isValidDeviceId(string $deviceId): bool
    {
        // Basic validation - customize based on your device ID format
        // This example expects UUID-like format or minimum length
        return strlen($deviceId) >= 16 && strlen($deviceId) <= 255;
    }
}