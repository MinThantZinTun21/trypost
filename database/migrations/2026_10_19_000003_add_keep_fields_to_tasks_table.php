<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep-style Tasks (ADR 0016, amended): the title becomes optional, and a
 * Task gains a colour and a pin. The Open list sorts pinned Tasks first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->string('color', 20)->nullable()->after('priority');
            $table->boolean('pinned')->default(false)->after('color');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['workspace_id', 'status', 'pinned', 'due_at']);
            $table->dropIndex(['workspace_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['workspace_id', 'status', 'due_at']);
            $table->dropIndex(['workspace_id', 'status', 'pinned', 'due_at']);
        });

        DB::table('tasks')->whereNull('title')->update(['title' => '']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['color', 'pinned']);
            $table->string('title')->nullable(false)->change();
        });
    }
};
