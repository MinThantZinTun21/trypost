<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveStream\FailLiveStream;
use App\Enums\LiveStream\Status;
use App\Jobs\RunLiveStream;
use App\Models\LiveStream;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class StartDueLiveStreams extends Command
{
    protected $signature = 'live-streams:start-due';

    protected $description = 'Hand due Live streams, and live ones left behind by a restart, to the live worker';

    /**
     * Hands out due scheduled Live streams, and live ones nobody is playing
     * any more (the worker restarted), which resume from the start of the
     * Video. A due one that finds another Live stream live fails instead.
     */
    public function handle(): void
    {
        $this->unclaimed(LiveStream::query())
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('status', Status::Scheduled)->where('starts_at', '<=', now()))
                ->orWhere('status', Status::Live))
            ->orderByRaw('case when status = ? then 0 else 1 end', [Status::Live->value])
            ->oldest('starts_at')
            ->each(function (LiveStream $liveStream): void {
                $claimed = $this->unclaimed(LiveStream::query()->whereKey($liveStream->id))
                    ->update(['heartbeat_at' => now()]);

                if (! $claimed) {
                    return;
                }

                if ($liveStream->status === Status::Scheduled && LiveStream::anotherIsPlaying($liveStream)) {
                    FailLiveStream::execute($liveStream, __('live_streams.one_at_a_time'));

                    return;
                }

                RunLiveStream::dispatch($liveStream);
            });
    }

    /**
     * @param  Builder<LiveStream>  $query
     * @return Builder<LiveStream>
     */
    private function unclaimed(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereNull('heartbeat_at')
            ->orWhere('heartbeat_at', '<', now()->subSeconds(LiveStream::STALE_AFTER_SECONDS)));
    }
}
