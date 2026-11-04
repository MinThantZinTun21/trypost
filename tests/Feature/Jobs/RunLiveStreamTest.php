<?php

declare(strict_types=1);

use App\Enums\LiveStream\Status;
use App\Jobs\RunLiveStream;
use App\Models\LiveStream;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Storage::fake();
    Sleep::fake();
    File::deleteDirectory(RunLiveStream::localDirectory());

    $this->workspace = Workspace::factory()->create();
    $this->video = Video::factory()->for($this->workspace)->withFile()->create();
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
        $copied = file_get_contents(RunLiveStream::localDirectory().'/'.$this->stream->id.'.mp4');

        return Process::result();
    });

    (new RunLiveStream($this->stream))->handle();

    Process::assertRan(fn (PendingProcess $process) => $process->command === [
        'ffmpeg', '-hide_banner', '-loglevel', 'warning', '-re', '-stream_loop', '-1',
        '-i', RunLiveStream::localDirectory().'/'.$this->stream->id.'.mp4',
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
        ->and(file_exists(RunLiveStream::localDirectory().'/'.$this->stream->id.'.mp4'))->toBeFalse();
});

test('it fails the live stream when ffmpeg exits on its own', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Connection refused', exitCode: 1)]);

    (new RunLiveStream($this->stream))->handle();

    $stream = $this->stream->fresh();
    expect($stream->status)->toBe(Status::Failed)
        ->and($stream->failure_reason)->toContain('Connection refused')
        ->and($stream->ended_at)->not->toBeNull();
});

test('it fails a live stream whose start finds another one live', function () {
    LiveStream::factory()->for($this->workspace)->live()->create();
    Process::fake();

    (new RunLiveStream($this->stream))->handle();

    Process::assertNothingRan();
    expect($this->stream->fresh()->status)->toBe(Status::Failed)
        ->and($this->stream->fresh()->failure_reason)->toBe(__('live_streams.one_at_a_time'));
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
