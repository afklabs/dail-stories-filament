<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create new ratings table
        Schema::create('member_story_ratings', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('story_id')->constrained('stories')->onDelete('cascade');
            $table->tinyInteger('rating')->unsigned();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'story_id'], 'member_story_rating_unique');

            // Indexes for performance
            $table->index(['story_id', 'rating']);
            $table->index(['story_id', 'created_at']);
            $table->index('rating');
        });

        // Add CHECK constraint on rating (1 to 5)
        DB::statement('ALTER TABLE member_story_ratings ADD CONSTRAINT rating_check CHECK (rating >= 1 AND rating <= 5)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_story_ratings');
    }
};
