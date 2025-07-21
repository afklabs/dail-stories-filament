<?php

// ===== Migration: enhance_member_story_interactions_table.php =====
// Run: php artisan make:migration enhance_member_story_interactions_table --table=member_story_interactions

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

            // Add composite indexes for performance
            $table->index(['member_id', 'action'], 'idx_member_action');
            $table->index(['story_id', 'action'], 'idx_story_action');
            $table->index('last_interacted_at', 'idx_last_interaction');
        });
    }

    public function down(): void
    {
        Schema::table('member_story_interactions', function (Blueprint $table) {
            $table->dropColumn(['interaction_count', 'last_interacted_at', 'metadata']);
            $table->dropIndex('idx_member_action');
            $table->dropIndex('idx_story_action');
            $table->dropIndex('idx_last_interaction');
        });
    }
};
