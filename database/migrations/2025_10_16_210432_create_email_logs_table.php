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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('email_type'); // welcome, password_reset, promotional
            $table->string('recipient_email')->index();
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('subject');
            $table->text('body')->nullable(); // Store email body for tracking
            $table->string('status')->default('pending'); // pending, sent, failed, bounced
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->integer('open_count')->default(0);
            $table->integer('click_count')->default(0);
            $table->string('tracking_id')->unique()->nullable(); // For tracking opens/clicks
            $table->json('metadata')->nullable(); // Additional data (campaign_id, etc.)
            $table->timestamps();

            // Indexes for performance
            $table->index(['email_type', 'status']);
            $table->index('sent_at');
            $table->index('tracking_id');
        });

        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('body'); // HTML content
            $table->string('status')->default('draft'); // draft, scheduled, sending, sent, failed
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->json('recipient_filters')->nullable(); // Store filter criteria
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('email_logs');
    }
};
