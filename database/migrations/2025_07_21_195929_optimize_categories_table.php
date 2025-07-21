<?php

// ===== Migration: optimize_categories_table.php =====
// Run: php artisan make:migration optimize_categories_table --table=categories

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            
            // Add performance indexes
            $table->index('slug', 'idx_categories_slug');
            $table->index('name', 'idx_categories_name');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['slug', 'description']);
            $table->dropIndex('idx_categories_slug');
            $table->dropIndex('idx_categories_name');
        });
    }
};