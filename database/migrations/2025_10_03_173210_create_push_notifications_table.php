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
    public function up(): void
    {
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();

            // Notification content
            $table->string('title');
            $table->text('body');

            // Targeting
            $table->enum('target_type', ['all', 'topic', 'tokens'])
                ->default('all')
                ->comment('Who receives: all users, specific topic, or device tokens');
            $table->text('target_value')
                ->nullable()
                ->comment('Topic name or comma-separated FCM tokens');

            // Additional data payload (JSON)
            $table->json('data')
                ->nullable()
                ->comment('Additional notification payload for navigation/actions');

            // Status tracking
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed'])
                ->default('draft')
                ->comment('Current notification status');

            // Scheduling
            $table->timestamp('scheduled_at')
                ->nullable()
                ->comment('When to send (null = send immediately)');
            $table->timestamp('sent_at')
                ->nullable()
                ->comment('When actually sent');

            // Delivery statistics
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);

            // Audit trail
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Admin user who created this notification');
            $table->foreignId('sent_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Admin user who triggered sending (if manual)');

            // Error tracking
            $table->text('error_message')
                ->nullable()
                ->comment('Error details if sending failed');

            // System information
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            // Performance indexes
            $table->index(['status', 'scheduled_at'], 'idx_status_schedule');
            $table->index(['created_by', 'created_at'], 'idx_creator_date');
            $table->index('scheduled_at', 'idx_scheduled_at');
            $table->index('sent_at', 'idx_sent_at');
            $table->index('status', 'idx_status');
            $table->index('target_type', 'idx_target_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};
