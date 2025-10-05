<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('story_rating_aggregates', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('story_rating_aggregates', 'verified_ratings_count')) {
                $table->unsignedInteger('verified_ratings_count')->nullable()->after('rating_distribution');
            }

            if (!Schema::hasColumn('story_rating_aggregates', 'verified_average_rating')) {
                $table->decimal('verified_average_rating', 3, 2)->nullable()->after('verified_ratings_count');
            }

            if (!Schema::hasColumn('story_rating_aggregates', 'comments_count')) {
                $table->unsignedInteger('comments_count')->nullable()->after('verified_average_rating');
            }

            if (!Schema::hasColumn('story_rating_aggregates', 'last_rated_at')) {
                $table->timestamp('last_rated_at')->nullable()->after('comments_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('story_rating_aggregates', function (Blueprint $table) {
            $columns = ['verified_ratings_count', 'verified_average_rating', 'comments_count', 'last_rated_at'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('story_rating_aggregates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
