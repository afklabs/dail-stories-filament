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
        Schema::create('member_story_interactions', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('story_id')->constrained('stories')->onDelete('cascade');
            $table->string('action', 20); // 'bookmark', 'share', 'view'
            $table->timestamps();

            // Unique constraint to prevent duplicate interactions
            $table->unique(['member_id', 'story_id', 'action'], 'member_story_action_unique');

            // Indexes for performance
            $table->index(['member_id', 'action']);
            $table->index(['story_id', 'action']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_story_interactions');
    }
};
