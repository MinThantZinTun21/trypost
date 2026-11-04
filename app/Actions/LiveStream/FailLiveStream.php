<?php

declare(strict_types=1);

namespace App\Actions\LiveStream;

use App\Enums\LiveStream\Status;
use App\Enums\Notification\Type;
use App\Jobs\SendNotification;
use App\Models\LiveStream;
use Illuminate\Support\Facades\File;

class FailLiveStream
{
    /**
     * Marks a scheduled or live Live stream Failed, removes the worker's
     * copy of its Video, ends its broadcast on YouTube and leaves the Owner
     * a Live stream failed Notification with the reason (ADR 0017). One
     * already Stopped stays stopped.
     */
    public static function execute(LiveStream $liveStream, string $reason): void
    {
        $failed = fn (Status $from): bool => (bool) LiveStream::query()
            ->whereKey($liveStream->id)
            ->where('status', $from)
            ->update(['status' => Status::Failed, 'ended_at' => now(), 'failure_reason' => $reason]);

        $wasLive = $failed(Status::Live);
        $wasScheduled = ! $wasLive && $failed(Status::Scheduled);

        $liveStream->refresh();
        File::delete($liveStream->localVideoPath());

        if (! $wasLive && ! $wasScheduled) {
            return;
        }

        EndLiveBroadcast::execute($liveStream, $wasLive);

        $owner = $liveStream->workspace?->owner;

        if ($owner === null) {
            return;
        }

        SendNotification::dispatch(
            user: $owner,
            workspaceId: $liveStream->workspace_id,
            type: Type::LiveStreamFailed,
            title: __('notifications.live_stream_failed.title'),
            body: __('notifications.live_stream_failed.body', ['title' => $liveStream->title, 'reason' => $reason]),
            data: ['live_stream_id' => $liveStream->id],
        );
    }
}
