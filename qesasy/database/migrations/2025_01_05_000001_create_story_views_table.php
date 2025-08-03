<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->onDelete('cascade');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->string('device_id', 64);
            $table->string('session_id', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('referrer')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['story_id', 'device_id', 'session_id']);
            $table->index(['story_id', 'viewed_at']);
            $table->index(['member_id', 'viewed_at']);
            $table->index('device_id');
            $table->index('viewed_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('story_views');
    }
};
