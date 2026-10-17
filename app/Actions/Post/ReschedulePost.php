<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Post\Status as PostStatus;
use App\Models\Post;
use App\Models\Workspace;
use App\Support\PostComposerValidator;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ReschedulePost
{
    /**
     * Schedules a Draft or moves a Scheduled post to $scheduledAt, keeping its
     * content, media, Post platforms and Content types, after the same checks
     * the composer runs before scheduling.
     *
     * @throws ValidationException
     */
    public static function execute(Workspace $workspace, Post $post, CarbonInterface $scheduledAt): Post
    {
        $payload = [
            ...PostComposerValidator::storedPayload($post),
            'status' => PostStatus::Scheduled->value,
            'scheduled_at' => $scheduledAt->copy()->utc()->toIso8601String(),
        ];

        PostComposerValidator::make($post, $payload)->validate();

        return data_get(UpdatePost::execute($workspace, $post, $payload), 'post');
    }
}
