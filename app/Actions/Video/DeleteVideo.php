<?php

declare(strict_types=1);

namespace App\Actions\Video;

use App\Models\LiveStream;
use App\Models\Video;
use Illuminate\Validation\ValidationException;

class DeleteVideo
{
    /**
     * Deletes the Video and its file, unless a scheduled or live Live stream
     * still plays it (ADR 0017).
     *
     * @throws ValidationException
     */
    public static function execute(Video $video): void
    {
        $liveStream = LiveStream::query()->active()->where('video_id', $video->id)->oldest('starts_at')->first();

        if ($liveStream !== null) {
            throw ValidationException::withMessages(['video' => __('videos.used_by_live_stream', ['title' => $liveStream->title])]);
        }

        $video->delete();
    }
}
