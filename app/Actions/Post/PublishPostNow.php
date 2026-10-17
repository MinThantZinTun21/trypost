<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Post\Status as PostStatus;
use App\Models\Post;
use App\Models\Workspace;
use App\Support\PostComposerValidator;
use Illuminate\Validation\ValidationException;

class PublishPostNow
{
    /**
     * Publishes a Scheduled post now through the composer's own path
     * (UpdatePost with status publishing), keeping its content, media, Post
     * platforms and Content types, after the composer's checks.
     *
     * @throws ValidationException
     */
    public static function execute(Workspace $workspace, Post $post): Post
    {
        $payload = [
            ...PostComposerValidator::storedPayload($post),
            'status' => PostStatus::Publishing->value,
        ];

        PostComposerValidator::make($post, $payload)->validate();

        return data_get(UpdatePost::execute($workspace, $post, $payload), 'post');
    }
}
