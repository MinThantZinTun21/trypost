<?php

declare(strict_types=1);

use App\Console\Commands\StartDueLiveStreams;
use App\Enums\LiveStream\Status;
use App\Enums\Notification\Type as NotificationType;
use App\Jobs\RunLiveStream;
use App\Models\LiveStream;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;
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

test('it hands a live stream nobody is playing any more back to the live worker', function () {
    Queue::fake();
    $orphaned = LiveStream::factory()->live()->create(['heartbeat_at' => now()->subMinutes(5)]);

    $this->artisan(StartDueLiveStreams::class)->assertSuccessful();

    Queue::assertPushedOn('live', RunLiveStream::class, fn (RunLiveStream $job) => $job->liveStream->is($orphaned));
});

test('it leaves a live stream that is still playing alone', function () {
    Queue::fake();
    LiveStream::factory()->live()->create(['heartbeat_at' => now()->subSeconds(10)]);

    $this->artisan(StartDueLiveStreams::class)->assertSuccessful();

    Queue::assertNothingPushed();
});

test('a due live stream that finds another one live fails with a notification', function () {
    Queue::fake([RunLiveStream::class]);
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    LiveStream::factory()->for($workspace)->live()->create(['heartbeat_at' => now()]);
    $due = LiveStream::factory()->for($workspace)->create(['starts_at' => now()->subMinute()]);

    $this->artisan(StartDueLiveStreams::class)->assertSuccessful();

    Queue::assertNothingPushed();
    expect($due->fresh()->status)->toBe(Status::Failed)
        ->and($due->fresh()->failure_reason)->toBe(__('live_streams.one_at_a_time'))
        ->and(Notification::query()->sole()->type)->toBe(NotificationType::LiveStreamFailed);
});
