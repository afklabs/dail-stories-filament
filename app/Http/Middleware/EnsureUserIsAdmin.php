<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure user has admin access for Filament admin routes
 */
class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'error' => 'Unauthenticated',
            ], 401);
        }

        $user = $request->user();

        // Check if user can access admin panel (using Filament's built-in method)
        if (method_exists($user, 'canAccessPanel')) {
            $panel = \Filament\Facades\Filament::getCurrentPanel();
            if (!$user->canAccessPanel($panel)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin access required',
                    'error' => 'Forbidden',
                ], 403);
            }
        }

        // Alternative: Check using Spatie Permission (if you're using it)
        // if (!$user->hasRole(['admin', 'super_admin'])) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Admin access required',
        //         'error' => 'Forbidden',
        //     ], 403);
        // }

        // Alternative: Simple status check for Member model
        if ($user instanceof \App\Models\Member) {
            if ($user->status !== 'active' || !$user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account verification required',
                    'error' => 'Account Unverified',
                ], 403);
            }
        }

        return $next($request);
    }
}