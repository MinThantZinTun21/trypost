<?php

declare(strict_types=1);

use App\Enums\LiveStream\Status;
use App\Enums\Notification\Type as NotificationType;
use App\Jobs\RunLiveStream;
use App\Models\LiveStream;
use App\Models\Notification;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Social\YouTubeLive;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Storage::fake();
    Sleep::fake();
    File::deleteDirectory(storage_path('app/live'));
    $this->youtube = Mockery::mock(YouTubeLive::class)->shouldIgnoreMissing();
    app()->instance(YouTubeLive::class, $this->youtube);

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->video = Video::factory()->for($this->workspace)->withFile(size: strlen('video bytes'))->create();
    Storage::put($this->video->file->path, 'video bytes');
    $this->stream = LiveStream::factory()->for($this->workspace)->create([
        'video_id' => $this->video->id,
        'starts_at' => now()->subMinute(),
        'ingest_url' => 'rtmps://a.rtmps.youtube.com/live2/key-789',
    ]);
});

test('it copies the video to local disk and plays it on repeat into the ingest url', function () {
    $copied = null;
    Process::fake(function (PendingProcess $process) use (&$copied) {
        $copied = file_get_contents($this->stream->localVideoPath());

        return Process::result();
    });

    (new RunLiveStream($this->stream))->handle();

    Process::assertRan(fn (PendingProcess $process) => $process->command === [
        'ffmpeg', '-hide_banner', '-loglevel', 'warning', '-re', '-stream_loop', '-1',
        '-i', $this->stream->localVideoPath(),
        '-c', 'copy', '-f', 'flv', 'rtmps://a.rtmps.youtube.com/live2/key-789',
    ]);
    expect($copied)->toBe('video bytes');
});

test('it marks the live stream live while ffmpeg runs', function () {
    $this->freezeTime();
    Process::fake(['*' => Process::describe()->iterations(3)]);
    $seen = [];
    Sleep::whenFakingSleep(function () use (&$seen) {
        $seen[] = $this->stream->fresh()->status;
    });

    (new RunLiveStream($this->stream))->handle();

    expect($seen)->not->toBeEmpty()
        ->and(collect($seen)->every(fn (Status $status) => $status === Status::Live))->toBeTrue();
});

test('it ends ffmpeg once the live stream is no longer live and removes the local copy', function () {
    Process::fake(['*' => Process::describe()->iterations(100)]);
    Sleep::whenFakingSleep(function () {
        $this->stream->update(['status' => Status::Stopped]);
    });

    (new RunLiveStream($this->stream))->handle();

    expect($this->stream->fresh()->status)->toBe(Status::Stopped)
        ->and(file_exists($this->stream->localVideoPath()))->toBeFalse();
});

test('it restarts ffmpeg after it exits, then fails with a notification once the retries run out', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Connection refused', exitCode: 1)]);

    (new RunLiveStream($this->stream))->handle();

    Process::assertRanTimes(fn (PendingProcess $process) => $process->command[0] === 'ffmpeg', RunLiveStream::MAX_RETRIES + 1);
    Sleep::assertSlept(fn ($duration) => (int) $duration->totalSeconds === RunLiveStream::RETRY_DELAY_SECONDS, RunLiveStream::MAX_RETRIES);

    $stream = $this->stream->fresh();
    expect($stream->status)->toBe(Status::Failed)
        ->and($stream->failure_reason)->toContain('Connection refused')
        ->and($stream->ended_at)->not->toBeNull();

    $notification = Notification::query()->sole();
    expect($notification->type)->toBe(NotificationType::LiveStreamFailed)
        ->and($notification->user_id)->toBe($this->owner->id)
        ->and($notification->body)->toContain('Connection refused')
        ->and($notification->data)->toEqual(['live_stream_id' => $this->stream->id]);
});

test('exits spread over more than the retry window never run out the retries', function () {
    $runs = 0;
    Process::fake(function () use (&$runs) {
        $runs++;
        $this->travel(RunLiveStream::RETRY_WINDOW_MINUTES)->minutes();

        if ($runs > RunLiveStream::MAX_RETRIES + 2) {
            $this->stream->update(['status' => Status::Stopped]);
        }

        return Process::result(exitCode: 1);
    });

    (new RunLiveStream($this->stream))->handle();

    expect($runs)->toBe(RunLiveStream::MAX_RETRIES + 3)
        ->and($this->stream->fresh()->status)->toBe(Status::Stopped)
        ->and(Notification::query()->exists())->toBeFalse();
});

test('it fails a live stream whose start finds another one live', function () {
    LiveStream::factory()->for($this->workspace)->live()->create();
    Process::fake();

    (new RunLiveStream($this->stream))->handle();

    Process::assertNothingRan();
    expect($this->stream->fresh()->status)->toBe(Status::Failed)
        ->and($this->stream->fresh()->failure_reason)->toBe(__('live_streams.one_at_a_time'))
        ->and(Notification::query()->sole()->type)->toBe(NotificationType::LiveStreamFailed);
});

test('a live stream left live by a restart plays again from its local copy', function () {
    $this->stream->update(['status' => Status::Live, 'started_at' => now()->subHour()]);
    File::ensureDirectoryExists(dirname($this->stream->localVideoPath()));
    File::put($this->stream->localVideoPath(), 'video bytes');
    $this->video->file->update(['size' => strlen('video bytes')]);
    Storage::delete($this->video->file->path);
    Process::fake(['*' => Process::describe()->iterations(2)]);
    Sleep::whenFakingSleep(function () {
        $this->stream->update(['status' => Status::Stopped]);
    });

    (new RunLiveStream($this->stream))->handle();

    Process::assertRanTimes(fn (PendingProcess $process) => $process->command[0] === 'ffmpeg', 1);
    expect($this->stream->fresh()->started_at->lt(now()->subMinutes(59)))->toBeTrue();
});

test('it fails when local disk has no room for the video', function () {
    Process::fake();
    $this->video->file->update(['size' => PHP_INT_MAX]);

    (new RunLiveStream($this->stream))->handle();

    Process::assertNothingRan();
    expect($this->stream->fresh()->status)->toBe(Status::Failed)
        ->and($this->stream->fresh()->failure_reason)->toContain('free disk');
});

test('it does nothing for a live stream that was stopped before it ran', function () {
    $this->stream->update(['status' => Status::Stopped]);
    Process::fake();

    (new RunLiveStream($this->stream))->handle();

    Process::assertNothingRan();
});

test('an unexpected exception fails the live stream and tells the owner', function () {
    (new RunLiveStream($this->stream))->failed(new RuntimeException('R2 read timed out'));

    expect($this->stream->fresh()->status)->toBe(Status::Failed)
        ->and($this->stream->fresh()->failure_reason)->toBe('R2 read timed out')
        ->and(Notification::query()->sole()->type)->toBe(NotificationType::LiveStreamFailed);
});

test('a failure never overwrites a stop, and removes the local copy', function () {
    $this->stream->update(['status' => Status::Stopped]);
    File::ensureDirectoryExists(dirname($this->stream->localVideoPath()));
    File::put($this->stream->localVideoPath(), 'video bytes');

    (new RunLiveStream($this->stream))->failed(new RuntimeException('late'));

    expect($this->stream->fresh()->status)->toBe(Status::Stopped)
        ->and(file_exists($this->stream->localVideoPath()))->toBeFalse()
        ->and(Notification::query()->exists())->toBeFalse();
});

test('it keeps the heartbeat fresh while it copies the video', function () {
    $this->stream->update(['heartbeat_at' => now()->subMinutes(10)]);
    Process::fake();

    (new RunLiveStream($this->stream))->handle();

    expect($this->stream->fresh()->heartbeat_at->gt(now()->subMinute()))->toBeTrue();
});

test('a failure ends the broadcast: a live one is completed, a scheduled one removed with its stream', function () {
    $account = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
    $this->stream->update(['status' => Status::Live, 'social_account_id' => $account->id]);
    $this->youtube->shouldReceive('complete')->once()->with(Mockery::any(), $this->stream->youtube_broadcast_id);

    (new RunLiveStream($this->stream))->failed(new RuntimeException('boom'));

    $scheduled = LiveStream::factory()->for($this->workspace)->create(['video_id' => $this->video->id, 'social_account_id' => $account->id]);
    $this->youtube->shouldReceive('delete')->once()
        ->with(Mockery::any(), $scheduled->youtube_broadcast_id, $scheduled->youtube_stream_id);

    (new RunLiveStream($scheduled))->failed(new RuntimeException('boom'));

    expect($this->stream->fresh()->status)->toBe(Status::Failed)
        ->and($scheduled->fresh()->status)->toBe(Status::Failed);
});

test('a truncated copy of the video fails the live stream instead of playing it', function () {
    $this->video->file->update(['size' => 999]);
    Process::fake();

    expect(fn () => (new RunLiveStream($this->stream))->handle())
        ->toThrow(RuntimeException::class, 'incomplete');

    Process::assertNothingRan();
    expect(file_exists($this->stream->localVideoPath()))->toBeFalse();
});
