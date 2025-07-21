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
        Schema::create('member_reading_history', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('story_id')->constrained('stories')->onDelete('cascade');
            $table->decimal('reading_progress', 5, 2)->default(0.00); // 0-100 percentage
            $table->integer('time_spent')->default(0); // seconds
            $table->timestamp('last_read_at');
            $table->timestamps();

            // Unique constraint to prevent duplicate history records
            $table->unique(['member_id', 'story_id'], 'member_story_history_unique');

            // Indexes for performance
            $table->index(['member_id', 'last_read_at']);
            $table->index(['story_id', 'reading_progress']);
            $table->index(['reading_progress', 'time_spent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_reading_history');
    }
};
