<?php

declare(strict_types=1);

namespace App\Actions\LiveStream;

use App\Exceptions\Social\YouTubeLiveException;
use App\Models\LiveStream;
use App\Services\Social\YouTubeLive;
use Illuminate\Support\Facades\Log;

class EndLiveBroadcast
{
    /**
     * Ends a Live stream's broadcast on YouTube (ADR 0017): one that went
     * live is completed, which keeps the recording; one that never did is
     * removed with its stream. YouTube refusing is logged, never thrown, so
     * the Live stream ends here either way.
     */
    public static function execute(LiveStream $liveStream, bool $wentLive): void
    {
        $account = $liveStream->socialAccount;

        if ($account === null || $liveStream->youtube_broadcast_id === null) {
            return;
        }

        try {
            $wentLive
                ? app(YouTubeLive::class)->complete($account, $liveStream->youtube_broadcast_id)
                : app(YouTubeLive::class)->delete($account, $liveStream->youtube_broadcast_id, $liveStream->youtube_stream_id);
        } catch (YouTubeLiveException $e) {
            Log::warning('YouTube refused to end a Live stream\'s broadcast', [
                'live_stream_id' => $liveStream->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
