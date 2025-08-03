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
        Schema::create('story_rating_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->unique()->constrained('stories')->onDelete('cascade');
            $table->unsignedInteger('total_ratings')->default(0);
            $table->unsignedInteger('sum_ratings')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0.00)->comment('Average rating with 2 decimal places');
            $table->json('rating_distribution')->nullable()->comment('Count of each rating (1-5 stars)');
            $table->timestamps();

            // Indexes for performance
            $table->index(['average_rating', 'total_ratings']);
            $table->index('total_ratings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('story_rating_aggregates');
    }
};
