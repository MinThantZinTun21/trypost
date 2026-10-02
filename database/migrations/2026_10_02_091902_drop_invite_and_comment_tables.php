<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teams, invites, post comments and @mentions are gone: drop the tables
     * and the column only they used, and the notifications whose type no
     * longer exists (their enum cases were removed).
     */
    public function up(): void
    {
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('invites');

        if (Schema::hasColumn('notification_preferences', 'mentioned_in_comment')) {
            Schema::table('notification_preferences', function (Blueprint $table): void {
                $table->dropColumn('mentioned_in_comment');
            });
        }

        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->whereIn('type', ['invite_received', 'member_joined', 'member_removed', 'mentioned_in_comment'])
                ->delete();
        }
    }

    public function down(): void
    {
        // Irreversible: the features these tables served are removed.
    }
};
