<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveStream\StopLiveStream;
use App\Models\LiveStream;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class StopDueLiveStreams extends Command
{
    protected $signature = 'live-streams:stop-due';

    protected $description = 'Stop Live streams whose stop time has come';

    /**
     * A Live stream the Owner Stopped in the meantime is skipped.
     */
    public function handle(): void
    {
        LiveStream::query()
            ->active()
            ->whereNotNull('stops_at')
            ->where('stops_at', '<=', now())
            ->each(function (LiveStream $liveStream): void {
                try {
                    StopLiveStream::execute($liveStream);
                } catch (ValidationException) {
                    return;
                }
            });
    }
}
