<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Post\Status as PostStatus;
use App\Models\Post;
use App\Models\Workspace;
use App\Support\PostComposerValidator;
use Illuminate\Validation\ValidationException;

class UnschedulePost
{
    /**
     * Turns a Scheduled post back into a Draft, keeping everything else,
     * including the time it was planned for.
     *
     * @throws ValidationException
     */
    public static function execute(Workspace $workspace, Post $post): Post
    {
        $payload = [
            ...PostComposerValidator::storedPayload($post),
            'status' => PostStatus::Draft->value,
        ];

        PostComposerValidator::make($post, $payload)->validate();

        return data_get(UpdatePost::execute($workspace, $post, $payload), 'post');
    }
}
