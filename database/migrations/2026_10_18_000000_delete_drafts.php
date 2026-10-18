<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * There are no Drafts (ADR 0011): every Draft goes, its Post platforms with
 * it through the cascade, and a Post saved without a status is Scheduled.
 * Media files stay; nothing else points at them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('posts')->where('status', 'draft')->delete();

        Schema::table('posts', function (Blueprint $table) {
            $table->string('status')->default('scheduled')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }
};
