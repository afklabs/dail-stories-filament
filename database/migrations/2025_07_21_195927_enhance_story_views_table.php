<?php

// ===== Migration: enhance_story_views_table.php =====
// Run: php artisan make:migration enhance_story_views_table --table=story_views

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_views', function (Blueprint $table) {
            // Add comprehensive tracking fields if they don't exist
            if (!Schema::hasColumn('story_views', 'referrer')) {
                $table->text('referrer')->nullable()->after('user_agent');
            }

            if (!Schema::hasColumn('story_views', 'metadata')) {
                $table->json('metadata')->nullable()->after('referrer');
            }
        });

        // Add indexes safely
        $this->addIndexSafely('story_views', ['story_id', 'viewed_at'], 'idx_story_views_date');
        $this->addIndexSafely('story_views', ['member_id', 'viewed_at'], 'idx_member_views_date');
        $this->addIndexSafely('story_views', ['device_id'], 'idx_device_views');
        $this->addIndexSafely('story_views', ['viewed_at'], 'idx_views_timestamp');
    }

    public function down(): void
    {
        Schema::table('story_views', function (Blueprint $table) {
            // Drop columns if they exist
            if (Schema::hasColumn('story_views', 'referrer')) {
                $table->dropColumn('referrer');
            }

            if (Schema::hasColumn('story_views', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });

        // Drop indexes safely
        $this->dropIndexSafely('story_views', 'idx_story_views_date');
        $this->dropIndexSafely('story_views', 'idx_member_views_date');
        $this->dropIndexSafely('story_views', 'idx_device_views');
        $this->dropIndexSafely('story_views', 'idx_views_timestamp');
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
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table}");
            foreach ($indexes as $index) {
                if ($index->Key_name === $indexName) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
};
