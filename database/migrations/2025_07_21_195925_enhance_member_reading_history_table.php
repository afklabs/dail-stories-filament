<?php

// ===== Migration: enhance_member_reading_history_table.php =====
// Run: php artisan make:migration enhance_member_reading_history_table --table=member_reading_history

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_reading_history', function (Blueprint $table) {
            // Add enhanced tracking fields
            if (!Schema::hasColumn('member_reading_history', 'reading_sessions')) {
                $table->integer('reading_sessions')->default(1)->after('time_spent');
            }

            if (!Schema::hasColumn('member_reading_history', 'bookmarks')) {
                $table->json('bookmarks')->nullable()->after('reading_sessions');
            }

            if (!Schema::hasColumn('member_reading_history', 'metadata')) {
                $table->json('metadata')->nullable()->after('bookmarks');
            }
        });

        // Add indexes safely
        $this->addIndexSafely('member_reading_history', ['member_id', 'last_read_at'], 'idx_reading_history_member_date');
        $this->addIndexSafely('member_reading_history', ['reading_progress'], 'idx_reading_progress');
    }

    public function down(): void
    {
        Schema::table('member_reading_history', function (Blueprint $table) {
            // Drop columns if they exist
            if (Schema::hasColumn('member_reading_history', 'reading_sessions')) {
                $table->dropColumn('reading_sessions');
            }

            if (Schema::hasColumn('member_reading_history', 'bookmarks')) {
                $table->dropColumn('bookmarks');
            }

            if (Schema::hasColumn('member_reading_history', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });

        // Drop indexes safely
        $this->dropIndexSafely('member_reading_history', 'idx_reading_history_member_date');
        $this->dropIndexSafely('member_reading_history', 'idx_reading_progress');
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
