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
 * (ADR 0004). A failed read records the error for the Insights page and
 * keeps the history; it never changes the Social account's connection status.
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

    public int $uniqueFor = 900;

    public function __construct(public SocialAccount $account) {}

    public function uniqueId(): string
    {
        return $this->account->id;
    }

    public function handle(FacebookInsights $insights): void
    {
        try {
            $this->storePageDays($insights);

            $this->account->update([
                'insights_read_at' => now(),
                'insights_error' => null,
                'insights_refresh_queued_at' => null,
            ]);
        } catch (InsightsReadException $e) {
            Log::warning('Facebook Insights read failed', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);

            $this->account->update([
                'insights_error' => $e->getMessage(),
                'insights_refresh_queued_at' => null,
            ]);
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
