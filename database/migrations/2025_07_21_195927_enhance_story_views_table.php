<?php

// ===== Migration: enhance_story_views_table.php =====
// Run: php artisan make:migration enhance_story_views_table --table=story_views

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

            // Add performance indexes
            $table->index(['story_id', 'viewed_at'], 'idx_story_views_date');
            $table->index(['member_id', 'viewed_at'], 'idx_member_views_date');
            $table->index('device_id', 'idx_device_views');
            $table->index('viewed_at', 'idx_views_timestamp');
        });
    }

    public function down(): void
    {
        Schema::table('story_views', function (Blueprint $table) {
            $table->dropColumn(['referrer', 'metadata']);
            $table->dropIndex('idx_story_views_date');
            $table->dropIndex('idx_member_views_date');
            $table->dropIndex('idx_device_views');
            $table->dropIndex('idx_views_timestamp');
        });
    }
};
