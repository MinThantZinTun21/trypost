<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timer sessions on Tasks (ADR 0016): a Pomodoro or a free-running timer.
 * At most one runs per workspace; the action checks it under a cache lock,
 * since MySQL has no partial unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timer_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('task_id');
            $table->string('mode', 20);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('planned_seconds')->nullable();
            $table->string('outcome', 20)->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->index(['workspace_id', 'ended_at']);
            $table->index(['task_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timer_sessions');
    }
};
