<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Jobs\ReadFacebookInsights;
use App\Models\SocialAccount;

class RefreshPageInsights
{
    /**
     * Queue an Insights read for one Facebook Page now, and mark it pending
     * so the Insights page polls until the read settles.
     */
    public static function execute(SocialAccount $account): void
    {
        $account->update(['insights_refresh_queued_at' => now()]);

        ReadFacebookInsights::dispatch($account);
    }
}
