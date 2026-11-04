<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LiveStream\Status;
use App\Jobs\RunLiveStream;
use App\Models\LiveStream;
use Illuminate\Console\Command;

class StartDueLiveStreams extends Command
{
    protected $signature = 'live-streams:start-due';

    protected $description = 'Hand Live streams whose start time has come to the live worker';

    public function handle(): void
    {
        LiveStream::query()
            ->where('status', Status::Scheduled)
            ->where('starts_at', '<=', now())
            ->whereNull('heartbeat_at')
            ->oldest('starts_at')
            ->each(function (LiveStream $liveStream): void {
                $claimed = LiveStream::query()
                    ->whereKey($liveStream->id)
                    ->whereNull('heartbeat_at')
                    ->update(['heartbeat_at' => now()]);

                if ($claimed) {
                    RunLiveStream::dispatch($liveStream);
                }
            });
    }
}
