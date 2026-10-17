<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Models\Post;
use App\Models\Workspace;
use App\Support\PostComposerValidator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class UpdatePostTexts
{
    /**
     * Changes a Draft's or Scheduled post's content and its Post platforms'
     * texts, keeping its status, time, media, Post platforms and Content types,
     * after the same checks the composer runs for that status. A null text
     * clears it, so the Post content stands in again.
     *
     * @param  array<string, mixed>  $data  `content` (optional) and `platforms`, texts keyed by Post platform id
     *
     * @throws ValidationException
     */
    public static function execute(Workspace $workspace, Post $post, array $data): Post
    {
        $payload = PostComposerValidator::storedPayload($post);

        if (Arr::has($data, 'content')) {
            $payload['content'] = data_get($data, 'content');
        }

        $texts = (array) data_get($data, 'platforms', []);

        $payload['platforms'] = collect($payload['platforms'])
            ->map(fn (array $platform): array => [
                ...$platform,
                'meta' => [...$platform['meta'], ...(array) data_get($texts, $platform['id'], [])],
            ])
            ->all();

        PostComposerValidator::make($post, $payload)->validate();

        return data_get(UpdatePost::execute($workspace, $post, $payload), 'post');
    }
}
