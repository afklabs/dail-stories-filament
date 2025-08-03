<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class BaseAdminController extends Controller
{
    /**
     * Admin-specific error response format
     * 
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array $errors Additional error details
     * @return JsonResponse
     */
    protected function adminErrorResponse(string $message, int $status = 500, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'context' => 'admin',
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        // Add debug info in development
        if (app()->environment('local', 'development')) {
            $response['debug'] = [
                'user_id' => Auth::id(),
                'route' => request()->route()?->getName(),
                'method' => request()->method(),
            ];
        }

        return response()->json($response, $status);
    }

    /**
     * Admin-specific success response format
     * 
     * @param array $data Response data
     * @param string $message Success message
     * @param array $meta Additional metadata
     * @return JsonResponse
     */
    protected function adminSuccessResponse(array $data, string $message = 'Success', array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'context' => 'admin',
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response);
    }

    /**
     * Check if user has required permissions
     * 
     * @param string $permission Permission name
     * @return bool
     */
    protected function checkPermission(string $permission): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Check if user has Spatie Permission methods
        if (method_exists($user, 'can')) {
            return $user->can($permission);
        }

        // Fallback - check if user is admin
        return $user->is_admin ?? false;
    }

    /**
     * Check if user has specific role
     * 
     * @param string $role Role name
     * @return bool
     */
    protected function checkRole(string $role): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Check if user has Spatie Permission hasRole method
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole($role);
        }

        // Fallback for basic admin check
        if ($role === 'admin' || $role === 'super_admin') {
            return $user->is_admin ?? false;
        }

        return false;
    }

    /**
     * Check if user has any of the required permissions
     * 
     * @param array $permissions Array of permission names
     * @return bool
     */
    protected function checkAnyPermission(array $permissions): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Check if user has Spatie Permission hasAnyPermission method
        if (method_exists($user, 'hasAnyPermission')) {
            return $user->hasAnyPermission($permissions);
        }

        // Fallback - check each permission individually
        foreach ($permissions as $permission) {
            if ($this->checkPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all required permissions
     * 
     * @param array $permissions Array of permission names
     * @return bool
     */
    protected function checkAllPermissions(array $permissions): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Check if user has Spatie Permission hasAllPermissions method
        if (method_exists($user, 'hasAllPermissions')) {
            return $user->hasAllPermissions($permissions);
        }

        // Fallback - check each permission individually
        foreach ($permissions as $permission) {
            if (!$this->checkPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has any of the specified roles
     * 
     * @param array $roles Array of role names
     * @return bool
     */
    protected function checkAnyRole(array $roles): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Check if user has Spatie Permission hasAnyRole method
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        // Fallback - check each role individually
        foreach ($roles as $role) {
            if ($this->checkRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all specified roles
     * 
     * @param array $roles Array of role names
     * @return bool
     */
    protected function checkAllRoles(array $roles): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Check if user has Spatie Permission hasAllRoles method
        if (method_exists($user, 'hasAllRoles')) {
            return $user->hasAllRoles($roles);
        }

        // Fallback - check each role individually
        foreach ($roles as $role) {
            if (!$this->checkRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rate limiting check for heavy operations
     * 
     * @param string $key Rate limit key
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $decayMinutes Minutes until reset
     * @return bool True if within rate limit
     */
    protected function checkRateLimit(string $key, int $maxAttempts = 10, int $decayMinutes = 1): bool
    {
        $userId = Auth::id();

        if (!$userId) {
            return false;
        }

        $rateLimitKey = "rate_limit:{$key}:{$userId}";

        $currentAttempts = Cache::get($rateLimitKey, 0);

        if ($currentAttempts >= $maxAttempts) {
            return false;
        }

        Cache::put($rateLimitKey, $currentAttempts + 1, now()->addMinutes($decayMinutes));

        return true;
    }

    /**
     * Get remaining rate limit attempts
     * 
     * @param string $key Rate limit key
     * @param int $maxAttempts Maximum attempts allowed
     * @return int Remaining attempts
     */
    protected function getRemainingAttempts(string $key, int $maxAttempts = 10): int
    {
        $userId = Auth::id();

        if (!$userId) {
            return 0;
        }

        $rateLimitKey = "rate_limit:{$key}:{$userId}";
        $currentAttempts = Cache::get($rateLimitKey, 0);

        return max(0, $maxAttempts - $currentAttempts);
    }

    /**
     * Reset rate limit for a specific key
     * 
     * @param string $key Rate limit key
     * @return bool Success status
     */
    protected function resetRateLimit(string $key): bool
    {
        $userId = Auth::id();

        if (!$userId) {
            return false;
        }

        $rateLimitKey = "rate_limit:{$key}:{$userId}";
        Cache::forget($rateLimitKey);

        return true;
    }

    /**
     * Get authenticated admin user safely
     * 
     * @return \App\Models\User|null
     */
    protected function getAuthenticatedUser()
    {
        return Auth::user();
    }

    /**
     * Get authenticated admin user ID safely
     * 
     * @return int|null
     */
    protected function getAuthenticatedUserId(): ?int
    {
        return Auth::id();
    }

    /**
     * Require authentication and return user
     * 
     * @return \App\Models\User
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function requireAuthenticate()
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'Authentication required');
        }

        return $user;
    }

    /**
     * Require specific permission
     * 
     * @param string $permission Permission name
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requirePermission(string $permission): void
    {
        if (!$this->checkPermission($permission)) {
            abort(403, "Permission required: {$permission}");
        }
    }

    /**
     * Require specific role
     * 
     * @param string $role Role name
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requireRole(string $role): void
    {
        if (!$this->checkRole($role)) {
            abort(403, "Role required: {$role}");
        }
    }

    /**
     * Require any of the specified permissions
     * 
     * @param array $permissions Array of permission names
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requireAnyPermission(array $permissions): void
    {
        if (!$this->checkAnyPermission($permissions)) {
            abort(403, 'Insufficient permissions');
        }
    }

    /**
     * Require all specified permissions
     * 
     * @param array $permissions Array of permission names
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requireAllPermissions(array $permissions): void
    {
        if (!$this->checkAllPermissions($permissions)) {
            abort(403, 'Insufficient permissions');
        }
    }

    /**
     * Require any of the specified roles
     * 
     * @param array $roles Array of role names
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requireAnyRole(array $roles): void
    {
        if (!$this->checkAnyRole($roles)) {
            abort(403, 'Insufficient roles');
        }
    }

    /**
     * Require rate limit check and abort if exceeded
     * 
     * @param string $key Rate limit key
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $decayMinutes Minutes until reset
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requireRateLimit(string $key, int $maxAttempts = 10, int $decayMinutes = 1): void
    {
        if (!$this->checkRateLimit($key, $maxAttempts, $decayMinutes)) {
            $remainingMinutes = $decayMinutes;
            abort(429, "Rate limit exceeded. Try again in {$remainingMinutes} minutes.");
        }
    }

    /**
     * Log admin action for audit trail
     * 
     * @param string $action Action performed
     * @param array $data Additional data
     * @return void
     */
    protected function logAdminAction(string $action, array $data = []): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return;
        }

        $logData = [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'action' => $action,
            'data' => $data,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'route' => request()->route()?->getName(),
            'method' => request()->method(),
            'timestamp' => now()->toISOString(),
        ];

        // Add user roles if available
        if (method_exists($user, 'getRoleNames')) {
            $logData['user_roles'] = $user->getRoleNames()->toArray();
        }

        Log::info('Admin action performed', $logData);
    }

    /**
     * Validate pagination parameters
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param int $maxPerPage Maximum items per page
     * @return array Validated parameters
     */
    protected function validatePagination(int $page = 1, int $perPage = 15, int $maxPerPage = 100): array
    {
        return [
            'page' => max(1, $page),
            'per_page' => min(max(1, $perPage), $maxPerPage),
        ];
    }

    /**
     * Build paginated response with metadata
     * 
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     * @return array Response with pagination metadata
     */
    protected function buildPaginatedResponse($paginator): array
    {
        return [
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * Handle common validation errors
     * 
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return JsonResponse
     */
    protected function handleValidationErrors($validator): JsonResponse
    {
        return $this->adminErrorResponse(
            'Validation failed',
            422,
            $validator->errors()->toArray()
        );
    }

    /**
     * Format date for admin responses
     * 
     * @param \Carbon\Carbon|null $date
     * @return string|null
     */
    protected function formatAdminDate($date): ?string
    {
        return $date?->format('Y-m-d H:i:s');
    }

    /**
     * Format date range for queries
     * 
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    protected function formatDateRange(?string $startDate, ?string $endDate): array
    {
        return [
            'start' => $startDate ? \Carbon\Carbon::parse($startDate)->startOfDay() : null,
            'end' => $endDate ? \Carbon\Carbon::parse($endDate)->endOfDay() : null,
        ];
    }

    /**
     * Check if user is super admin
     * 
     * @return bool
     */
    protected function isSuperAdmin(): bool
    {
        return $this->checkRole('super_admin');
    }

    /**
     * Check if user is admin (any admin role)
     * 
     * @return bool
     */
    protected function isAdmin(): bool
    {
        return $this->checkAnyRole(['super_admin', 'admin']);
    }

    /**
     * Get user's permissions (safely)
     * 
     * @return array
     */
    protected function getUserPermissions(): array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        // Check if user has Spatie Permission getAllPermissions method
        if (method_exists($user, 'getAllPermissions')) {
            return $user->getAllPermissions()->pluck('name')->toArray();
        }

        return [];
    }

    /**
     * Get user's roles (safely)
     * 
     * @return array
     */
    protected function getUserRoles(): array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        // Check if user has Spatie Permission getRoleNames method
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->toArray();
        }

        return [];
    }
}
