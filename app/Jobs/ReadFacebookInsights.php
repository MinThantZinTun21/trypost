<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Insights\PageMetric;
use App\Exceptions\Social\InsightsReadException;
use App\Models\SocialAccount;
use App\Services\Social\Meta\FacebookInsights;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Reads one Facebook Page's Insights and stores them as Insights snapshots
 * (ADR 0004), and Post insights for its Posts published in the last 30
 * days. A failed Page read records the error for the Insights page and
 * keeps the history; it never changes the Social account's connection status.
 *
 * The refresh mark is cleared only once the Post reads are done too, so the
 * Insights page keeps polling until top Posts are current.
 */
class ReadFacebookInsights implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Days read the first time, so the dashboard has history at once. */
    public const BACKFILL_DAYS = 90;

    /** Days re-read after that, because Facebook revises recent days. */
    public const RECENT_DAYS = 3;

    public int $tries = 1;

    public int $timeout = 300;

    /** How long a queued read blocks another, and counts as pending. */
    public const UNIQUE_FOR_SECONDS = 900;

    public int $uniqueFor = self::UNIQUE_FOR_SECONDS;

    public function __construct(public SocialAccount $account) {}

    public function uniqueId(): string
    {
        return $this->account->id;
    }

    /** Days after publishing that a Post's insights keep being read. */
    public const POST_DAYS = 30;

    public function handle(FacebookInsights $insights): void
    {
        $this->account->update(['insights_attempted_at' => now()]);

        try {
            $this->readPage($insights);
            $this->readPosts($insights);
        } finally {
            $this->account->update(['insights_refresh_queued_at' => null]);
        }
    }

    private function readPage(FacebookInsights $insights): void
    {
        try {
            $this->storePageDays($insights);

            $this->account->update([
                'insights_read_at' => now(),
                'insights_error' => null,
            ]);
        } catch (InsightsReadException $e) {
            Log::warning('Facebook Insights read failed', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);

            $this->account->update(['insights_error' => $e->getMessage()]);
        }
    }

    /**
     * Each Post platform is read on its own: one Post Facebook refuses (for
     * example a video still processing) does not stop the others. A resolved
     * feed post id is stored before the totals are read, so a failed read
     * does not look it up again next time.
     */
    private function readPosts(FacebookInsights $insights): void
    {
        $postPlatforms = $this->account->postPlatforms()
            ->readsInsights()
            ->with(['insight', 'post'])
            ->get();

        foreach ($postPlatforms as $postPlatform) {
            try {
                $feedPostId = $postPlatform->insight?->feed_post_id;

                if ($feedPostId === null) {
                    $feedPostId = $insights->feedPostId($this->account, $postPlatform);
                    $postPlatform->insight()->updateOrCreate([], ['feed_post_id' => $feedPostId]);
                }

                $totals = $insights->postTotals($this->account, $postPlatform, $feedPostId);

                $postPlatform->insight()->updateOrCreate([], [...$totals, 'read_at' => now()]);
            } catch (InsightsReadException $e) {
                Log::warning('Facebook Post insights read failed', [
                    'post_platform_id' => $postPlatform->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function storePageDays(FacebookInsights $insights): void
    {
        $days = $this->account->pageInsightSnapshots()->exists() ? self::RECENT_DAYS : self::BACKFILL_DAYS;
        $until = now()->subDay()->startOfDay()->toImmutable();

        foreach ($insights->pageDays($this->account, $until->subDays($days - 1), $until) as $date => $values) {
            $this->account->pageInsightSnapshots()->updateOrCreate(
                ['date' => $date],
                collect(PageMetric::columns())->mapWithKeys(fn (string $column): array => [$column => data_get($values, $column)])->all(),
            );
        }
    }
}
