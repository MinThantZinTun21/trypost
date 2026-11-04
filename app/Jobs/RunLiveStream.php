<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\LiveStream\Status;
use App\Models\LiveStream;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Plays one Live stream (ADR 0017) on the `live` worker: copies its Video
 * from R2 to local disk, then runs ffmpeg in copy mode on repeat into the
 * YouTube ingest URL until the Live stream stops being live. It runs for
 * hours, so it lives on its own connection and worker with no timeout, and
 * proves it is alive through heartbeat_at instead of the queue's reservation.
 */
class RunLiveStream implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 0;

    /** Seconds between checks on ffmpeg and the Live stream. */
    public const int TICK_SECONDS = 5;

    /** Free disk kept on top of the Video's size. */
    private const int DISK_HEADROOM_BYTES = 512 * 1024 * 1024;

    private const int SIGTERM = 15;

    public function __construct(public LiveStream $liveStream)
    {
        $this->onConnection('live');
        $this->onQueue('live');
    }

    public static function localDirectory(): string
    {
        return storage_path('app/live');
    }

    public function handle(): void
    {
        $liveStream = $this->liveStream->refresh();

        if (! in_array($liveStream->status, Status::active(), true)) {
            return;
        }

        $otherIsLive = LiveStream::query()->live()->whereKeyNot($liveStream->id)->exists();

        if ($otherIsLive) {
            $this->markFailed(__('live_streams.one_at_a_time'));

            return;
        }

        $path = self::localDirectory()."/{$liveStream->id}.mp4";

        try {
            if (! $this->copyVideo($path)) {
                return;
            }

            $liveStream->update([
                'status' => Status::Live,
                'started_at' => $liveStream->started_at ?? now(),
                'heartbeat_at' => now(),
            ]);

            $process = Process::forever()->start([
                'ffmpeg', '-hide_banner', '-loglevel', 'warning', '-re', '-stream_loop', '-1',
                '-i', $path,
                '-c', 'copy', '-f', 'flv', (string) $liveStream->ingest_url,
            ]);

            $this->watch($process);
        } finally {
            if ($liveStream->refresh()->status !== Status::Live) {
                File::delete($path);
            }
        }
    }

    /**
     * Copies the Video to local disk, unless an earlier run left it there.
     */
    private function copyVideo(string $path): bool
    {
        $file = $this->liveStream->video?->file;

        if ($file === null) {
            $this->markFailed(__('live_streams.no_file'));

            return false;
        }

        if (File::exists($path) && File::size($path) === (int) $file->size) {
            return true;
        }

        File::ensureDirectoryExists(self::localDirectory());
        $free = (int) disk_free_space(self::localDirectory());

        if ($free < (int) $file->size + self::DISK_HEADROOM_BYTES) {
            $this->markFailed(__('live_streams.no_disk', [
                'needed' => Number::fileSize((int) $file->size + self::DISK_HEADROOM_BYTES),
                'free' => Number::fileSize($free),
            ]));

            return false;
        }

        $source = Storage::readStream($file->path);
        $target = fopen($path, 'wb');
        stream_copy_to_stream($source, $target);
        fclose($target);
        fclose($source);

        return true;
    }

    /**
     * Keeps the heartbeat fresh while ffmpeg runs, and ends ffmpeg once the
     * Live stream is no longer live (it was Stopped).
     */
    private function watch(InvokedProcess $process): void
    {
        while ($process->running()) {
            Sleep::for(self::TICK_SECONDS)->seconds();

            $this->liveStream->refresh();

            if ($this->liveStream->status !== Status::Live) {
                $process->signal(self::SIGTERM);
                $process->wait();

                return;
            }

            $this->liveStream->update(['heartbeat_at' => now()]);
        }

        $result = $process->wait();

        if ($this->liveStream->refresh()->status === Status::Live) {
            $this->markFailed(__('live_streams.ffmpeg_exited', [
                'code' => (string) $result->exitCode(),
                'output' => Str::limit(trim($result->errorOutput() ?: $result->output()), 500),
            ]));
        }
    }

    private function markFailed(string $reason): void
    {
        $this->liveStream->update([
            'status' => Status::Failed,
            'ended_at' => now(),
            'failure_reason' => $reason,
        ]);
    }
}
