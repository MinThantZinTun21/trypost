<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Telegram mirror of every published Post is gone (ADR 0009): only
 * TikTok targets go to Telegram now, through the publish job.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('notifications')->where('type', 'telegram_failed')->delete();

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('telegram_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('telegram_sent_at')->nullable()->after('published_at');
        });
    }
};
