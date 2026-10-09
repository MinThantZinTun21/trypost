<?php

declare(strict_types=1);

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
        Schema::create('post_insights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('post_platform_id')->unique();
            $table->string('feed_post_id')->nullable();
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('reactions')->nullable();
            $table->unsignedBigInteger('clicks')->nullable();
            $table->unsignedBigInteger('video_views')->nullable();
            $table->unsignedBigInteger('avg_watch_time_ms')->nullable();
            $table->unsignedBigInteger('watch_time_ms')->nullable();
            $table->unsignedBigInteger('reel_plays')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('post_platform_id')->references('id')->on('post_platforms')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_insights');
    }
};
