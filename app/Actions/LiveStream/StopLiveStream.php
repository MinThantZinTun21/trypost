<?php

declare(strict_types=1);

namespace App\Actions\LiveStream;

use App\Enums\LiveStream\Status;
use App\Exceptions\Social\YouTubeLiveException;
use App\Models\LiveStream;
use App\Services\Social\YouTubeLive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StopLiveStream
{
    /**
     * Stops a Live stream (ADR 0017). A live one is marked stopped first, so
     * the live worker ends ffmpeg on its next check, then its broadcast is
     * completed on YouTube, which keeps the recording. A scheduled one has
     * its broadcast removed. YouTube refusing never keeps it running.
     *
     * @throws ValidationException
     */
    public static function execute(LiveStream $liveStream): LiveStream
    {
        $wasLive = (bool) LiveStream::query()
            ->whereKey($liveStream->id)
            ->where('status', Status::Live)
            ->update(['status' => Status::Stopped, 'ended_at' => now()]);

        $stopped = $wasLive || LiveStream::query()
            ->whereKey($liveStream->id)
            ->where('status', Status::Scheduled)
            ->update(['status' => Status::Stopped, 'ended_at' => now()]);

        if (! $stopped) {
            throw ValidationException::withMessages(['id' => __('live_streams.not_active')]);
        }

        $liveStream->refresh();

        if (! $wasLive) {
            File::delete($liveStream->localVideoPath());
        }
        $account = $liveStream->socialAccount;

        if ($account !== null && $liveStream->youtube_broadcast_id !== null) {
            try {
                $wasLive
                    ? app(YouTubeLive::class)->complete($account, $liveStream->youtube_broadcast_id)
                    : app(YouTubeLive::class)->delete($account, $liveStream->youtube_broadcast_id);
            } catch (YouTubeLiveException $e) {
                Log::warning('YouTube refused to end a Live stream\'s broadcast', [
                    'live_stream_id' => $liveStream->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $liveStream;
    }
}
