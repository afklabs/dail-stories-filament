<?php

// ===== Migration: enhance_member_reading_history_table.php =====
// Run: php artisan make:migration enhance_member_reading_history_table --table=member_reading_history

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

            // Add performance indexes
            $table->index(['member_id', 'last_read_at'], 'idx_reading_history_member_date');
            $table->index('reading_progress', 'idx_reading_progress');
        });
    }

    public function down(): void
    {
        Schema::table('member_reading_history', function (Blueprint $table) {
            $table->dropColumn(['reading_sessions', 'bookmarks', 'metadata']);
            $table->dropIndex('idx_reading_history_member_date');
            $table->dropIndex('idx_reading_progress');
        });
    }
};
