<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to handle API versioning and compatibility
 */
class ApiVersionMiddleware
{
    /**
     * Supported API versions
     */
    private const SUPPORTED_VERSIONS = ['v1'];
    private const DEFAULT_VERSION = 'v1';
    private const MINIMUM_SUPPORTED_VERSION = 'v1';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API version from various sources
        $version = $this->extractApiVersion($request);

        // Validate version
        if (!$this->isVersionSupported($version)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported API version',
                'error' => 'Version Not Supported',
                'supported_versions' => self::SUPPORTED_VERSIONS,
                'requested_version' => $version,
            ], 400);
        }

        // Check if version is deprecated (for future use)
        if ($this->isVersionDeprecated($version)) {
            // Add deprecation warning header
            $response = $next($request);
            $response->headers->set('X-API-Deprecated', 'true');
            $response->headers->set('X-API-Sunset-Date', '2025-12-31'); // Example
            return $response;
        }

        // Add version info to request
        $request->merge([
            'api_version' => $version,
            'api_version_numeric' => $this->getVersionNumber($version),
        ]);

        // Add version to response headers
        $response = $next($request);
        $response->headers->set('X-API-Version', $version);
        $response->headers->set('X-API-Supported-Versions', implode(',', self::SUPPORTED_VERSIONS));

        return $response;
    }

    /**
     * Extract API version from request
     */
    private function extractApiVersion(Request $request): string
    {
        // 1. Check URL prefix (already handled by routing)
        if (str_contains($request->path(), '/api/v')) {
            preg_match('/\/api\/(v\d+)\//', $request->path(), $matches);
            if (isset($matches[1])) {
                return $matches[1];
            }
        }

        // 2. Check Accept header (e.g., application/vnd.dailystories.v1+json)
        $acceptHeader = $request->header('Accept');
        if ($acceptHeader && preg_match('/vnd\.dailystories\.(v\d+)\+json/', $acceptHeader, $matches)) {
            return $matches[1];
        }

        // 3. Check custom header
        $versionHeader = $request->header('X-API-Version');
        if ($versionHeader && in_array($versionHeader, self::SUPPORTED_VERSIONS)) {
            return $versionHeader;
        }

        // 4. Check query parameter
        $versionParam = $request->query('version');
        if ($versionParam && in_array($versionParam, self::SUPPORTED_VERSIONS)) {
            return $versionParam;
        }

        // 5. Default version
        return self::DEFAULT_VERSION;
    }

    /**
     * Check if version is supported
     */
    private function isVersionSupported(string $version): bool
    {
        return in_array($version, self::SUPPORTED_VERSIONS);
    }

    /**
     * Check if version is deprecated (for future use)
     */
    private function isVersionDeprecated(string $version): bool
    {
        // Example: v1 is deprecated if v2 exists
        // return $version === 'v1' && in_array('v2', self::SUPPORTED_VERSIONS);
        return false; // No deprecated versions yet
    }

    /**
     * Get numeric version for comparison
     */
    private function getVersionNumber(string $version): int
    {
        return (int) str_replace('v', '', $version);
    }
}