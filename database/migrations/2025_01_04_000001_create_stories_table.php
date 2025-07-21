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
        Schema::create('stories', function (Blueprint $table)
        {
            $table->id();
            $table->string('title');
            $table->longText('content');
            $table->text('excerpt')->nullable();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('image')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->integer('reading_time_minutes')->default(5);
            $table->boolean('active')->default(false);
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_until')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['active', 'active_from', 'active_until']);
            $table->index(['category_id', 'active']);
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
        Schema::dropIfExists('stories');
    }
};
