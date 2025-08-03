<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enhanced API Version Middleware with comprehensive version handling
 * 
 * Handles API versioning through multiple detection methods:
 * - URL path segments (/api/v1/, /api/v2/)
 * - Accept headers (application/vnd.dailystories.v1+json)
 * - Custom headers (X-API-Version)
 * - Query parameters (?version=v1)
 * 
 * Features:
 * - Multiple version detection methods
 * - Version validation and compatibility checking
 * - Deprecation warnings with sunset dates
 * - Comprehensive error responses
 * - Request/response header management
 * - Fallback to default version
 * 
 * @author Development Team
 * @version 2.0.0
 */
class ApiVersionMiddleware
{
    /**
     * Supported API versions in priority order
     */
    private const SUPPORTED_VERSIONS = ['v1', 'v2']; // Future-ready for v2

    /**
     * Default version when none specified
     */
    private const DEFAULT_VERSION = 'v1';

    /**
     * Minimum supported version (older versions rejected)
     */
    private const MINIMUM_SUPPORTED_VERSION = 'v1';

    /**
     * Deprecated versions with sunset information
     */
    private const DEPRECATED_VERSIONS = [
        // 'v1' => '2025-12-31', // Example: v1 deprecated, sunset on 2025-12-31
    ];

    /**
     * Version-specific configuration
     */
    private const VERSION_CONFIG = [
        'v1' => [
            'min_laravel_version' => '11.0',
            'features' => ['basic_auth', 'story_reading', 'member_management'],
            'rate_limits' => ['default' => 60, 'auth' => 120],
        ],
        'v2' => [
            'min_laravel_version' => '11.0',
            'features' => ['basic_auth', 'story_reading', 'member_management', 'advanced_analytics'],
            'rate_limits' => ['default' => 100, 'auth' => 200],
        ],
    ];

    /**
     * Handle an incoming request with comprehensive version detection
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Extract API version from various sources
            $version = $this->extractApiVersion($request);

            // Log version detection for debugging
            if (config('app.debug')) {
                Log::debug('API Version Detection', [
                    'detected_version' => $version,
                    'request_path' => $request->path(),
                    'accept_header' => $request->header('Accept'),
                    'version_header' => $request->header('X-API-Version'),
                    'query_version' => $request->query('version'),
                ]);
            }

            // Validate version support
            if (!$this->isVersionSupported($version)) {
                return $this->createVersionErrorResponse($version, 'unsupported');
            }

            // Check minimum version requirement
            if (!$this->isVersionCompatible($version)) {
                return $this->createVersionErrorResponse($version, 'too_old');
            }

            // Add version information to request
            $this->addVersionToRequest($request, $version);

            // Process the request
            $response = $next($request);

            // Add version headers to response
            $this->addVersionHeaders($response, $version);

            // Handle deprecated versions
            if ($this->isVersionDeprecated($version)) {
                $this->addDeprecationHeaders($response, $version);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('API Version Middleware Error', [
                'error' => $e->getMessage(),
                'request_path' => $request->path(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback to default version on error
            $request->merge([
                'api_version' => self::DEFAULT_VERSION,
                'api_version_numeric' => $this->getVersionNumber(self::DEFAULT_VERSION),
            ]);

            return $next($request);
        }
    }

    /**
     * Extract API version from multiple sources with priority
     */
    private function extractApiVersion(Request $request): string
    {
        // Priority 1: URL path segments (most reliable)
        $pathVersion = $this->extractVersionFromPath($request);
        if ($pathVersion && $this->isValidVersionFormat($pathVersion)) {
            return $pathVersion;
        }

        // Priority 2: Accept header (content negotiation)
        $acceptVersion = $this->extractVersionFromAcceptHeader($request);
        if ($acceptVersion && $this->isValidVersionFormat($acceptVersion)) {
            return $acceptVersion;
        }

        // Priority 3: Custom header (explicit version request)
        $headerVersion = $this->extractVersionFromHeader($request);
        if ($headerVersion && $this->isValidVersionFormat($headerVersion)) {
            return $headerVersion;
        }

        // Priority 4: Query parameter (fallback method)
        $queryVersion = $this->extractVersionFromQuery($request);
        if ($queryVersion && $this->isValidVersionFormat($queryVersion)) {
            return $queryVersion;
        }

        // Priority 5: Default version
        return self::DEFAULT_VERSION;
    }

    /**
     * Extract version from URL path
     */
    private function extractVersionFromPath(Request $request): ?string
    {
        $path = $request->path();

        // Match patterns like /api/v1/, /api/v2/
        if (preg_match('/\/api\/(v\d+)(?:\/|$)/', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract version from Accept header
     */
    private function extractVersionFromAcceptHeader(Request $request): ?string
    {
        $acceptHeader = $request->header('Accept');

        if (!$acceptHeader) {
            return null;
        }

        // Match patterns like application/vnd.dailystories.v1+json
        if (preg_match('/vnd\.dailystories\.(v\d+)\+json/', $acceptHeader, $matches)) {
            return $matches[1];
        }

        // Match patterns like application/json;version=v1
        if (preg_match('/version=(v\d+)/', $acceptHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract version from custom header
     */
    private function extractVersionFromHeader(Request $request): ?string
    {
        $versionHeader = $request->header('X-API-Version');

        if (!$versionHeader) {
            return null;
        }

        // Clean and validate header value
        $version = trim(strtolower($versionHeader));

        // Ensure v prefix
        if (is_numeric($version)) {
            $version = 'v' . $version;
        }

        return $version;
    }

    /**
     * Extract version from query parameter
     */
    private function extractVersionFromQuery(Request $request): ?string
    {
        $versionParam = $request->query('version');

        if (!$versionParam) {
            return null;
        }

        // Clean and validate parameter value
        $version = trim(strtolower($versionParam));

        // Ensure v prefix
        if (is_numeric($version)) {
            $version = 'v' . $version;
        }

        return $version;
    }

    /**
     * Validate version format
     */
    private function isValidVersionFormat(string $version): bool
    {
        return preg_match('/^v\d+$/', $version) === 1;
    }

    /**
     * Check if version is supported
     */
    private function isVersionSupported(string $version): bool
    {
        return in_array($version, self::SUPPORTED_VERSIONS, true);
    }

    /**
     * Check if version meets minimum requirements
     */
    private function isVersionCompatible(string $version): bool
    {
        $versionNumber = $this->getVersionNumber($version);
        $minimumNumber = $this->getVersionNumber(self::MINIMUM_SUPPORTED_VERSION);

        return $versionNumber >= $minimumNumber;
    }

    /**
     * Check if version is deprecated
     */
    private function isVersionDeprecated(string $version): bool
    {
        return array_key_exists($version, self::DEPRECATED_VERSIONS);
    }

    /**
     * Get numeric version for comparison
     */
    private function getVersionNumber(string $version): int
    {
        return (int) str_replace('v', '', $version);
    }

    /**
     * Add version information to request
     */
    private function addVersionToRequest(Request $request, string $version): void
    {
        $versionData = [
            'api_version' => $version,
            'api_version_numeric' => $this->getVersionNumber($version),
            'api_version_config' => self::VERSION_CONFIG[$version] ?? [],
        ];

        $request->merge($versionData);

        // Also make it available as request attributes
        foreach ($versionData as $key => $value) {
            $request->attributes->set($key, $value);
        }
    }

    /**
     * Add version headers to response
     */
    private function addVersionHeaders(Response $response, string $version): void
    {
        $response->headers->set('X-API-Version', $version);
        $response->headers->set('X-API-Supported-Versions', implode(',', self::SUPPORTED_VERSIONS));
        $response->headers->set('X-API-Min-Version', self::MINIMUM_SUPPORTED_VERSION);

        // Add version-specific capabilities
        if (isset(self::VERSION_CONFIG[$version]['features'])) {
            $response->headers->set('X-API-Features', implode(',', self::VERSION_CONFIG[$version]['features']));
        }
    }

    /**
     * Add deprecation headers for deprecated versions
     */
    private function addDeprecationHeaders(Response $response, string $version): void
    {
        $response->headers->set('X-API-Deprecated', 'true');

        if (isset(self::DEPRECATED_VERSIONS[$version])) {
            $response->headers->set('X-API-Sunset-Date', self::DEPRECATED_VERSIONS[$version]);
            $response->headers->set('X-API-Deprecation-Info', 'This API version will be discontinued on ' . self::DEPRECATED_VERSIONS[$version]);
        }

        // Add deprecation warning to JSON responses
        if ($response->headers->get('Content-Type') === 'application/json') {
            $content = json_decode($response->getContent(), true);
            if (is_array($content)) {
                $content['_deprecation'] = [
                    'version' => $version,
                    'deprecated' => true,
                    'sunset_date' => self::DEPRECATED_VERSIONS[$version] ?? null,
                    'message' => 'This API version is deprecated. Please upgrade to the latest version.',
                ];
                $response->setContent(json_encode($content));
            }
        }
    }

    /**
     * Create comprehensive error response for version issues
     */
    private function createVersionErrorResponse(string $version, string $errorType): Response
    {
        $errorMessages = [
            'unsupported' => 'Unsupported API version',
            'too_old' => 'API version too old and no longer supported',
            'invalid_format' => 'Invalid API version format',
        ];

        $statusCodes = [
            'unsupported' => 400,
            'too_old' => 410, // Gone
            'invalid_format' => 400,
        ];

        $errorData = [
            'success' => false,
            'error' => 'Version Error',
            'message' => $errorMessages[$errorType] ?? 'Version handling error',
            'details' => [
                'requested_version' => $version,
                'supported_versions' => self::SUPPORTED_VERSIONS,
                'minimum_version' => self::MINIMUM_SUPPORTED_VERSION,
                'default_version' => self::DEFAULT_VERSION,
                'deprecated_versions' => array_keys(self::DEPRECATED_VERSIONS),
            ],
            'recommendations' => $this->getVersionRecommendations($version, $errorType),
        ];

        $statusCode = $statusCodes[$errorType] ?? 400;

        $response = response()->json($errorData, $statusCode);

        // Add helpful headers even for error responses
        $response->headers->set('X-API-Supported-Versions', implode(',', self::SUPPORTED_VERSIONS));
        $response->headers->set('X-API-Min-Version', self::MINIMUM_SUPPORTED_VERSION);
        $response->headers->set('X-API-Default-Version', self::DEFAULT_VERSION);

        return $response;
    }

    /**
     * Get version-specific recommendations for errors
     */
    private function getVersionRecommendations(string $version, string $errorType): array
    {
        $recommendations = [];

        switch ($errorType) {
            case 'unsupported':
                $recommendations[] = 'Use one of the supported versions: ' . implode(', ', self::SUPPORTED_VERSIONS);
                $recommendations[] = 'Check the API documentation for migration guide';
                break;

            case 'too_old':
                $recommendations[] = 'Upgrade to the minimum supported version: ' . self::MINIMUM_SUPPORTED_VERSION;
                $recommendations[] = 'Review breaking changes in the changelog';
                break;

            case 'invalid_format':
                $recommendations[] = 'Use valid version format like: v1, v2';
                $recommendations[] = 'Version should be specified as "v" followed by a number';
                break;
        }

        return $recommendations;
    }

    /**
     * Get version configuration for a specific version
     */
    public static function getVersionConfig(string $version): array
    {
        return self::VERSION_CONFIG[$version] ?? [];
    }

    /**
     * Check if a feature is available in a specific version
     */
    public static function hasFeature(string $version, string $feature): bool
    {
        $config = self::getVersionConfig($version);
        return in_array($feature, $config['features'] ?? [], true);
    }

    /**
     * Get rate limits for a specific version
     */
    public static function getRateLimits(string $version): array
    {
        $config = self::getVersionConfig($version);
        return $config['rate_limits'] ?? ['default' => 60, 'auth' => 120];
    }
}
