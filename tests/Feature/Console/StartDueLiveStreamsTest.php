<?php

declare(strict_types=1);

use App\Console\Commands\StartDueLiveStreams;
use App\Jobs\RunLiveStream;
use App\Models\LiveStream;
use Illuminate\Support\Facades\Queue;

test('it dispatches due scheduled live streams to the live queue once', function () {
    Queue::fake();
    $due = LiveStream::factory()->create(['starts_at' => now()->subMinute()]);
    LiveStream::factory()->create(['starts_at' => now()->addHour()]);

    $this->artisan(StartDueLiveStreams::class)->assertSuccessful();
    $this->artisan(StartDueLiveStreams::class)->assertSuccessful();

    Queue::assertPushedOn('live', RunLiveStream::class, fn (RunLiveStream $job) => $job->liveStream->is($due));
    Queue::assertPushed(RunLiveStream::class, 1);
    expect($due->fresh()->heartbeat_at)->not->toBeNull();
});
