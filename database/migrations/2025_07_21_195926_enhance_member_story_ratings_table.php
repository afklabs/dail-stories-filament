<?php

// ===== Migration: enhance_member_story_ratings_table.php =====
// Run: php artisan make:migration enhance_member_story_ratings_table --table=member_story_ratings

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_story_ratings', function (Blueprint $table) {
            // Add verification and metadata fields
            if (!Schema::hasColumn('member_story_ratings', 'is_verified')) {
                $table->boolean('is_verified')->default(true)->after('comment');
            }

            if (!Schema::hasColumn('member_story_ratings', 'metadata')) {
                $table->json('metadata')->nullable()->after('is_verified');
            }
        });

        // Add indexes safely - check if they exist first
        $this->addIndexSafely('member_story_ratings', ['story_id', 'rating'], 'idx_story_rating');
        $this->addIndexSafely('member_story_ratings', ['member_id', 'created_at'], 'idx_member_rating_date');
        $this->addIndexSafely('member_story_ratings', ['is_verified'], 'idx_verified_ratings');
    }

    public function down(): void
    {
        Schema::table('member_story_ratings', function (Blueprint $table) {
            // Drop columns if they exist
            if (Schema::hasColumn('member_story_ratings', 'is_verified')) {
                $table->dropColumn('is_verified');
            }

            if (Schema::hasColumn('member_story_ratings', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });

        // Drop indexes safely - check if they exist first
        $this->dropIndexSafely('member_story_ratings', 'idx_story_rating');
        $this->dropIndexSafely('member_story_ratings', 'idx_member_rating_date');
        $this->dropIndexSafely('member_story_ratings', 'idx_verified_ratings');
    }

    /**
     * Add index safely - only if it doesn't exist
     */
    private function addIndexSafely(string $table, array $columns, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($columns, $indexName) {
                $tableBlueprint->index($columns, $indexName);
            });
        }
    }

    /**
     * Drop index safely - only if it exists
     */
    private function dropIndexSafely(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($indexName) {
                $tableBlueprint->dropIndex($indexName);
            });
        }
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table}");
        foreach ($indexes as $index) {
            if ($index->Key_name === $indexName) {
                return true;
            }
        }
        return false;
    }
};
