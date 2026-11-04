<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live streams (ADR 0017): a Video played on repeat as a live broadcast on
 * one YouTube channel. The Video and Social account stay nullable so a
 * finished Live stream outlives them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('user_id')->nullable();
            $table->uuid('video_id')->nullable();
            $table->uuid('social_account_id')->nullable();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->string('privacy');
            $table->string('status');
            $table->timestamp('starts_at');
            $table->timestamp('stops_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->string('youtube_broadcast_id')->nullable();
            $table->string('youtube_stream_id')->nullable();
            $table->text('ingest_url')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('video_id')->references('id')->on('videos')->nullOnDelete();
            $table->foreign('social_account_id')->references('id')->on('social_accounts')->nullOnDelete();
            $table->index(['workspace_id', 'created_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_streams');
    }
};
