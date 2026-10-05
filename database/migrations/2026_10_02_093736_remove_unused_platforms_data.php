<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private const array KEPT_PLATFORMS = ['facebook', 'tiktok', 'youtube'];

    /**
     * @var array<int, string>
     */
    private const array KEPT_CONTENT_TYPES = [
        'facebook_post',
        'facebook_reel',
        'facebook_story',
        'tiktok_video',
        'tiktok_photo',
        'youtube_short',
    ];

    /**
     * Only Facebook, TikTok and YouTube remain. Rows for any other platform or
     * content type would fail their enum cast on read, so they are deleted
     * (post targets before the accounts that own them). The Google Business
     * review statuses map to failed, and the review-tracking columns they used
     * are dropped.
     */
    public function up(): void
    {
        DB::table('post_platforms')
            ->where(fn ($query) => $query
                ->whereNotIn('platform', self::KEPT_PLATFORMS)
                ->orWhereNotIn('content_type', self::KEPT_CONTENT_TYPES))
            ->delete();

        $removedAccountIds = DB::table('social_accounts')
            ->whereNotIn('platform', self::KEPT_PLATFORMS)
            ->pluck('id');

        $removedAccountIds->chunk(500)->each(function ($ids): void {
            DB::table('post_platforms')->whereIn('social_account_id', $ids)->delete();
            DB::table('social_accounts')->whereIn('id', $ids)->delete();
        });

        DB::table('post_platforms')
            ->whereIn('status', ['pending_review', 'rejected'])
            ->update(['status' => 'failed']);

        if (Schema::hasIndex('post_platforms', ['status', 'last_reconciled_at'])) {
            Schema::table('post_platforms', function (Blueprint $table): void {
                $table->dropIndex(['status', 'last_reconciled_at']);
            });
        }

        foreach (['last_reconciled_at', 'submitted_at'] as $column) {
            if (Schema::hasColumn('post_platforms', $column)) {
                Schema::table('post_platforms', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        // Irreversible: the platforms these rows belonged to are removed.
    }
};
