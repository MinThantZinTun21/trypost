<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders on Tasks (ADR 0016): each rings at a fixed time or a number of
 * minutes before the Task's due, until the Owner dismisses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->string('type', 20);
            $table->timestamp('remind_at');
            $table->unsignedInteger('minutes_before')->nullable();
            $table->boolean('repeat_until_dismissed')->default(false);
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->index(['task_id', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
