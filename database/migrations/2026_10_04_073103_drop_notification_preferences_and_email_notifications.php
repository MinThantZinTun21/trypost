<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The app sends no email and only notifies in-app about failures and
     * accounts that need reconnecting: drop the email preferences, delete the
     * notifications whose type no longer exists, and move every stored row to
     * the only channel left so the enum cast can still read it.
     */
    public function up(): void
    {
        Schema::dropIfExists('notification_preferences');

        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->whereIn('type', ['post_published', 'post_partially_published'])
                ->delete();

            DB::table('notifications')
                ->where('channel', '!=', 'in_app')
                ->update(['channel' => 'in_app']);
        }
    }

    public function down(): void
    {
        // Irreversible: email notifications and their preferences are removed.
    }
};
