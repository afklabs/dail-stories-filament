<?php

// ===== Migration: enhance_member_story_ratings_table.php =====
// Run: php artisan make:migration enhance_member_story_ratings_table --table=member_story_ratings

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

            // Add indexes for analytics
            $table->index(['story_id', 'rating'], 'idx_story_rating');
            $table->index(['member_id', 'created_at'], 'idx_member_rating_date');
            $table->index('is_verified', 'idx_verified_ratings');
        });
    }

    public function down(): void
    {
        Schema::table('member_story_ratings', function (Blueprint $table) {
            $table->dropColumn(['is_verified', 'metadata']);
            $table->dropIndex('idx_story_rating');
            $table->dropIndex('idx_member_rating_date');
            $table->dropIndex('idx_verified_ratings');
        });
    }
};
    