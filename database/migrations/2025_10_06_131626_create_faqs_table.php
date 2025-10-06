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
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question'); // FAQ question text
            $table->text('answer'); // HTML rich text answer
            $table->integer('order')->default(0); // Display order (lower = shown first)
            $table->boolean('is_active')->default(true); // Only active FAQs shown to members
            $table->timestamps();

            // Indexes for performance
            $table->index(['is_active', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
