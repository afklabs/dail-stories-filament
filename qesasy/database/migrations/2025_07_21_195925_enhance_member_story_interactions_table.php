<?php

// ===== Migration: enhance_member_story_interactions_table.php =====
// Run: php artisan make:migration enhance_member_story_interactions_table --table=member_story_interactions

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_story_interactions', function (Blueprint $table) {
            // Add enhanced interaction tracking
            if (!Schema::hasColumn('member_story_interactions', 'interaction_count')) {
                $table->integer('interaction_count')->default(1)->after('action');
            }

            if (!Schema::hasColumn('member_story_interactions', 'last_interacted_at')) {
                $table->timestamp('last_interacted_at')->nullable()->after('interaction_count');
            }

            if (!Schema::hasColumn('member_story_interactions', 'metadata')) {
                $table->json('metadata')->nullable()->after('last_interacted_at');
            }
        });

        // Add indexes safely - check if they exist first
        $this->addIndexSafely('member_story_interactions', ['member_id', 'action'], 'idx_member_action');
        $this->addIndexSafely('member_story_interactions', ['story_id', 'action'], 'idx_story_action');
        $this->addIndexSafely('member_story_interactions', ['last_interacted_at'], 'idx_last_interaction');
    }

    public function down(): void
    {
        Schema::table('member_story_interactions', function (Blueprint $table) {
            // Drop columns if they exist
            if (Schema::hasColumn('member_story_interactions', 'interaction_count')) {
                $table->dropColumn('interaction_count');
            }

            if (Schema::hasColumn('member_story_interactions', 'last_interacted_at')) {
                $table->dropColumn('last_interacted_at');
            }

            if (Schema::hasColumn('member_story_interactions', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });

        // Drop indexes safely - check if they exist first
        $this->dropIndexSafely('member_story_interactions', 'idx_member_action');
        $this->dropIndexSafely('member_story_interactions', 'idx_story_action');
        $this->dropIndexSafely('member_story_interactions', 'idx_last_interaction');
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
            // If we can't check, assume it doesn't exist
            return false;
        }
    }
};
