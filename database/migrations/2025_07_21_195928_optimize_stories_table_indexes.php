<?php

// ===== Migration: optimize_stories_table_indexes.php =====
// Run: php artisan make:migration optimize_stories_table_indexes --table=stories

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Add performance indexes for common queries
            $table->index(['active', 'active_from', 'active_until'], 'idx_stories_active_period');
            $table->index(['category_id', 'active'], 'idx_stories_category_active');
            $table->index(['views', 'active'], 'idx_stories_views_active');
            $table->index(['created_at', 'active'], 'idx_stories_created_active');
            
            // Full-text search index for better search performance
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE stories ADD FULLTEXT search_index (title, excerpt, content)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropIndex('idx_stories_active_period');
            $table->dropIndex('idx_stories_category_active');
            $table->dropIndex('idx_stories_views_active');
            $table->dropIndex('idx_stories_created_active');
            
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE stories DROP INDEX search_index');
            }
        });
    }
};
