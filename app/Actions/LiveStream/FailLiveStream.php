<?php

declare(strict_types=1);

namespace App\Actions\LiveStream;

use App\Enums\LiveStream\Status;
use App\Enums\Notification\Type;
use App\Jobs\SendNotification;
use App\Models\LiveStream;

class FailLiveStream
{
    /**
     * Marks a Live stream Failed and leaves the Owner a Live stream failed
     * Notification with the reason (ADR 0017).
     */
    public static function execute(LiveStream $liveStream, string $reason): void
    {
        $liveStream->update([
            'status' => Status::Failed,
            'ended_at' => now(),
            'failure_reason' => $reason,
        ]);

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
