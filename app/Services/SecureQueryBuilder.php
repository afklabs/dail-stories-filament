<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SecureQueryBuilder
{
    /**
     * Apply secure search to query
     */
    public static function applySearch(Builder $query, string $search, array $columns): Builder
    {
        // Sanitize search term
        $search = self::sanitizeSearchTerm($search);

        if (empty($search))
        {
            return $query;
        }

        return $query->where(function ($q) use ($search, $columns)
        {
            foreach ($columns as $column)
            {
                // Validate column name to prevent injection
                if (!self::isValidColumnName($column))
                {
                    continue;
                }

                // Use parameter binding for search
                $q->orWhere($column, 'like', '%' . $search . '%');
            }
        });
    }

    /**
     * Sanitize search term to prevent SQL wildcard injection
     */
    public static function sanitizeSearchTerm(string $search): string
    {
        // Remove SQL wildcards
        $search = str_replace(['%', '_', '[', ']', '^', '-'], '', $search);

        // Remove any potential SQL keywords
        $sqlKeywords = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'UNION', 'EXEC', 'SCRIPT'];
        foreach ($sqlKeywords as $keyword)
        {
            $search = str_ireplace($keyword, '', $search);
        }

        // Trim and limit length
        return Str::limit(trim($search), 100);
    }

    /**
     * Validate column name to prevent SQL injection
     */
    public static function isValidColumnName(string $column): bool
    {
        // Only allow alphanumeric, underscore, and dot (for relations)
        return preg_match('/^[a-zA-Z0-9_\.]+$/', $column) === 1;
    }

    /**
     * Build safe ORDER BY clause
     */
    public static function applySorting(Builder $query, ?string $sortBy, ?string $sortOrder): Builder
    {
        // Define allowed sort columns per model
        $allowedSorts = [
            'App\Models\Story' => ['id', 'title', 'views', 'created_at', 'updated_at'],
            'App\Models\Member' => ['id', 'name', 'email', 'created_at'],
            'App\Models\MemberStoryInteraction' => ['created_at', 'action'],
        ];

        $modelClass = get_class($query->getModel());
        $allowed = $allowedSorts[$modelClass] ?? ['id', 'created_at'];

        // Validate sort column
        if (!$sortBy || !in_array($sortBy, $allowed))
        {
            $sortBy = 'created_at';
        }

        // Validate sort order
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Safely execute raw SQL with parameter binding
     */
    public static function safeRawQuery(string $sql, array $bindings = []): array
    {
        // Validate SQL doesn't contain dangerous keywords
        $dangerousKeywords = ['DROP', 'TRUNCATE', 'ALTER', 'GRANT', 'REVOKE'];
        foreach ($dangerousKeywords as $keyword)
        {
            if (stripos($sql, $keyword) !== false)
            {
                throw new \Exception('Dangerous SQL keyword detected');
            }
        }

        // Use parameter binding
        return DB::select($sql, $bindings);
    }
}
