<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handle Cross-Origin Resource Sharing (CORS) for API routes
 */
class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }

        // Apply CORS headers to API routes only
        if ($request->is('api/*') || $request->is('admin/api/*')) {
            $response->headers->set('Access-Control-Allow-Origin', $this->getAllowedOrigin($request));
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, X-Token-Auth, Authorization, Accept, X-Device-ID, X-API-Version');
            $response->headers->set('Access-Control-Allow-Credentials', 'false');
            $response->headers->set('Access-Control-Max-Age', '3600');
            $response->headers->set('Access-Control-Expose-Headers', 'X-API-Version, X-RateLimit-Limit, X-RateLimit-Remaining');
        }

        return $response;
    }

    /**
     * Get the allowed origin based on environment
     */
    private function getAllowedOrigin(Request $request): string
    {
        // In development, allow all origins
        if (app()->environment('local', 'development')) {
            return '*';
        }

        // In production, restrict to specific origins
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:8080',
            'http://10.0.2.2:3000', // Android emulator
            // Add your production Flutter app URL here
        ];

        $origin = $request->header('Origin');

        if ($origin && in_array($origin, $allowedOrigins)) {
            return $origin;
        }

        // Default to first allowed origin or restrict completely
        return $allowedOrigins[0] ?? '';
    }
}