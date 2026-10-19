<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks in the Mobile app (ADR 0016): the Owner's to-dos, with Task tags
 * sorted into Tag groups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('user_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->unsignedTinyInteger('priority')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->boolean('due_all_day')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['workspace_id', 'status', 'due_at']);
            $table->index(['workspace_id', 'status', 'completed_at']);
        });

        Schema::create('tag_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('name', 100);
            $table->string('color', 20);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('task_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tag_group_id');
            $table->string('name', 100);
            $table->timestamps();

            $table->foreign('tag_group_id')->references('id')->on('tag_groups')->cascadeOnDelete();
            $table->unique(['tag_group_id', 'name']);
        });

        Schema::create('task_task_tag', function (Blueprint $table) {
            $table->uuid('task_id');
            $table->uuid('task_tag_id');

            $table->primary(['task_id', 'task_tag_id']);
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('task_tag_id')->references('id')->on('task_tags')->cascadeOnDelete();
            $table->index('task_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_task_tag');
        Schema::dropIfExists('task_tags');
        Schema::dropIfExists('tag_groups');
        Schema::dropIfExists('tasks');
    }
};
