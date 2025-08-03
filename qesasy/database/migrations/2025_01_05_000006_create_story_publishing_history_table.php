<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('story_publishing_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Action type
            $table->enum('action', [
                'published',      // First time published
                'unpublished',    // Unpublished
                'republished',    // Republished
                'updated',        // Publishing data updated
                'scheduled',      // Scheduled for publishing
                'expired',         // Publishing expired
            ]);

            // Previous and new state
            $table->boolean('previous_active_status')->nullable();
            $table->boolean('new_active_status');

            // Previous and new dates
            $table->timestamp('previous_active_from')->nullable();
            $table->timestamp('previous_active_until')->nullable();
            $table->timestamp('new_active_from')->nullable();
            $table->timestamp('new_active_until')->nullable();

            // Additional information
            $table->text('notes')->nullable()->comment('Admin notes on the change');
            $table->json('changed_fields')->nullable()->comment('Fields that were changed');

            // System information
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            // Performance indexes
            $table->index(['story_id', 'action']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('story_publishing_history');
    }
};
