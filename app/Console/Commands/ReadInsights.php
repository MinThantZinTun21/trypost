<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Jobs\ReadFacebookInsights;
use App\Models\SocialAccount;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class ReadInsights extends Command
{
    protected $signature = 'insights:read';

    protected $description = 'Queue an Insights read for every connected Facebook Page';

    public function handle(): void
    {
        SocialAccount::query()
            ->where('platform', Platform::Facebook)
            ->where('status', Status::Connected)
            ->chunkById(100, function (Collection $accounts): void {
                foreach ($accounts as $account) {
                    ReadFacebookInsights::dispatch($account);
                }
            });
    }
}
