<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_story_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->string('story_title', 255);
            $table->longText('story_content');
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');

            $table->enum('submission_status', ['pending', 'archived', 'published', 'rejected'])
                ->default('pending')
                ->index();

            $table->text('admin_notes')->nullable();
            $table->foreignId('published_story_id')->nullable()->constrained('stories')->onDelete('set null');

            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // Indexes for performance
            $table->index(['member_id', 'submission_status']);
            $table->index(['category_id', 'submitted_at']);
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_story_submissions');
    }
};
