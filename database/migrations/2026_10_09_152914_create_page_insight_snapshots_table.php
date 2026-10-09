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
        Schema::create('page_insight_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('social_account_id');
            $table->date('date');
            $table->unsignedBigInteger('followers')->nullable();
            $table->unsignedBigInteger('new_follows')->nullable();
            $table->unsignedBigInteger('unfollows')->nullable();
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('engagements')->nullable();
            $table->unsignedBigInteger('video_views')->nullable();
            $table->timestamps();

            $table->foreign('social_account_id')->references('id')->on('social_accounts')->cascadeOnDelete();

            $table->unique(['social_account_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_insight_snapshots');
    }
};
