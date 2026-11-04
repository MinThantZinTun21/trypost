<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\LiveStream\FailLiveStream;
use App\Enums\LiveStream\Status;
use App\Models\LiveStream;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Plays one Live stream (ADR 0017) on the `live` worker: copies its Video
 * from R2 to local disk, then runs ffmpeg in copy mode on repeat into the
 * YouTube ingest URL until the Live stream stops being live. It runs for
 * hours, so it lives on its own connection and worker with no timeout, and
 * proves it is alive through heartbeat_at instead of the queue's reservation.
 * An ffmpeg that exits on its own is restarted up to MAX_RETRIES times within
 * RETRY_WINDOW_MINUTES before the Live stream fails.
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

    /** ffmpeg is restarted at most this many times within RETRY_WINDOW_MINUTES. */
    public const int MAX_RETRIES = 5;

    public const int RETRY_WINDOW_MINUTES = 10;

    /** Seconds to wait before restarting ffmpeg. */
    public const int RETRY_DELAY_SECONDS = 10;

    private const int SIGTERM = 15;

    private const int COPY_CHUNK_BYTES = 8 * 1024 * 1024;

    private ?CarbonInterface $lastBeat = null;

    public function __construct(public LiveStream $liveStream)
    {
        $this->onConnection('live');
        $this->onQueue('live');
    }

    public function handle(): void
    {
        $liveStream = $this->liveStream->refresh();

        if (! in_array($liveStream->status, Status::active(), true)) {
            return;
        }

        if (LiveStream::anotherIsPlaying($liveStream)) {
            $this->markFailed(__('live_streams.one_at_a_time'));

            return;
        }

        $path = $liveStream->localVideoPath();

        try {
            if (! $this->copyVideo($path)) {
                return;
            }

            $wentLive = LiveStream::query()
                ->whereKey($liveStream->id)
                ->whereIn('status', Status::active())
                ->update([
                    'status' => Status::Live,
                    'started_at' => $liveStream->started_at ?? now(),
                    'heartbeat_at' => now(),
                ]);

            if ($wentLive) {
                $this->play($path);
            }
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

        File::ensureDirectoryExists(dirname($this->liveStream->localVideoPath()));
        $free = (int) disk_free_space(dirname($this->liveStream->localVideoPath()));

        if ($free < (int) $file->size + self::DISK_HEADROOM_BYTES) {
            $this->markFailed(__('live_streams.no_disk', [
                'needed' => Number::fileSize((int) $file->size + self::DISK_HEADROOM_BYTES),
                'free' => Number::fileSize($free),
            ]));

            return false;
        }

        $source = Storage::readStream($file->path);

        if ($source === null) {
            $this->markFailed(__('live_streams.no_file'));

            return false;
        }

        $target = fopen($path, 'wb');

        if ($target === false) {
            fclose($source);

            throw new RuntimeException("Could not open {$path} for writing.");
        }

        try {
            while (! feof($source)) {
                $chunk = (string) fread($source, self::COPY_CHUNK_BYTES);

                if (fwrite($target, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException("Could not write the Video to {$path}.");
                }

                if (! $this->beat()) {
                    return false;
                }
            }
        } finally {
            fclose($target);
            fclose($source);
        }

        clearstatcache(true, $path);

        if (File::size($path) !== (int) $file->size) {
            throw new RuntimeException("The copy of the Video at {$path} is incomplete.");
        }

        return true;
    }

    /**
     * Freshens heartbeat_at at most once per TICK_SECONDS, so a long copy is
     * not mistaken for a dead worker. Returns false once the Live stream is
     * no longer scheduled or live (it was Stopped), so the copy ends early.
     */
    private function beat(): bool
    {
        if ($this->lastBeat !== null && $this->lastBeat->gt(now()->subSeconds(self::TICK_SECONDS))) {
            return true;
        }

        $this->lastBeat = now();

        return (bool) LiveStream::query()
            ->whereKey($this->liveStream->id)
            ->whereIn('status', Status::active())
            ->update(['heartbeat_at' => now()]);
    }

    /**
     * An unexpected exception fails the Live stream instead of leaving it to
     * be handed out again and again.
     */
    public function failed(Throwable $exception): void
    {
        FailLiveStream::execute($this->liveStream, Str::limit($exception->getMessage(), 500));
    }

    /**
     * Runs ffmpeg, restarting it after an unexpected exit until the retries
     * run out or the Live stream stops being live.
     */
    private function play(string $path): void
    {
        $exits = [];

        while (true) {
            $process = Process::forever()->start([
                'ffmpeg', '-hide_banner', '-loglevel', 'warning', '-re', '-stream_loop', '-1',
                '-i', $path,
                '-c', 'copy', '-f', 'flv', (string) $this->liveStream->ingest_url,
            ]);

            try {
                $failure = $this->watch($process);
            } catch (Throwable $e) {
                if ($process->running()) {
                    $process->signal(self::SIGTERM);
                }

                throw $e;
            }

            if ($failure === null) {
                return;
            }

            $exits = array_values(array_filter($exits, fn (CarbonInterface $at): bool => $at->gt(now()->subMinutes(self::RETRY_WINDOW_MINUTES))));
            $exits[] = now();

            if (count($exits) > self::MAX_RETRIES) {
                $this->markFailed($failure);

                return;
            }

            Sleep::for(self::RETRY_DELAY_SECONDS)->seconds();

            if ($this->liveStream->refresh()->status !== Status::Live) {
                return;
            }
        }
    }

    /**
     * Keeps the heartbeat fresh while ffmpeg runs, and ends ffmpeg once the
     * Live stream is no longer live (it was Stopped). Returns why ffmpeg
     * exited on its own, or null when it was ended on purpose.
     */
    private function watch(InvokedProcess $process): ?string
    {
        while ($process->running()) {
            Sleep::for(self::TICK_SECONDS)->seconds();

            $this->liveStream->refresh();

            if ($this->liveStream->status !== Status::Live) {
                $process->signal(self::SIGTERM);
                $process->wait();

                return null;
            }

            $this->liveStream->update(['heartbeat_at' => now()]);
        }

        $result = $process->wait();

        if ($this->liveStream->refresh()->status !== Status::Live) {
            return null;
        }

        return __('live_streams.ffmpeg_exited', [
            'code' => (string) $result->exitCode(),
            'output' => Str::limit(trim($result->errorOutput() ?: $result->output()), 500),
        ]);
    }

    private function markFailed(string $reason): void
    {
        FailLiveStream::execute($this->liveStream, $reason);
    }
}
