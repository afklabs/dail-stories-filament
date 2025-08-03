<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Add slug for SEO if not exists
            if (!Schema::hasColumn('categories', 'slug')) {
                $table->string('slug')->unique()->nullable()->after('name');
            }

            // Add description if not exists
            if (!Schema::hasColumn('categories', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
        });

        // Add indexes separately to avoid conflicts
        $this->addIndexIfNotExists('categories', 'slug', 'idx_categories_slug');
        $this->addIndexIfNotExists('categories', 'name', 'idx_categories_name');
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Drop indexes first if they exist
            $this->dropIndexIfExists('categories', 'idx_categories_slug');
            $this->dropIndexIfExists('categories', 'idx_categories_name');

            // Drop columns if they exist
            if (Schema::hasColumn('categories', 'slug')) {
                $table->dropColumn('slug');
            }
            if (Schema::hasColumn('categories', 'description')) {
                $table->dropColumn('description');
            }
        });
    }

    /**
     * Add index if it doesn't already exist
     */
    private function addIndexIfNotExists(string $table, string $column, string $indexName): void
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
        if (empty($indexes)) {
            DB::statement("CREATE INDEX {$indexName} ON {$table} ({$column})");
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
        if (!empty($indexes)) {
            DB::statement("DROP INDEX {$indexName} ON {$table}");
        }
    }
};
