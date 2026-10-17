<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Post\Action as PostAction;
use App\Enums\Post\CreatedVia;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PostComposerValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateComposedPost
{
    /**
     * Creates a Post that goes out: scheduled, or publishing now (ADR 0011).
     * The payload is the composer's, except that each platform names its
     * Social account (`social_account_id`), because no Post platform exists
     * yet. The Post is created, checked with the composer's own update rules
     * and handed to UpdatePost; a Post that fails the checks is rolled back,
     * so nothing is saved that would not go out.
     *
     * @param  array{
     *     status: string,
     *     content?: ?string,
     *     media?: array<int, mixed>,
     *     scheduled_at?: ?string,
     *     platforms: array<int, array{social_account_id: string, content_type?: ?string, meta?: array<string, mixed>}>
     * }  $payload
     * @return array{post: Post, action: PostAction|null}
     *
     * @throws ValidationException
     */
    public static function execute(Workspace $workspace, User $user, array $payload, CreatedVia $createdVia): array
    {
        return DB::transaction(function () use ($workspace, $user, $payload, $createdVia): array {
            $post = CreatePost::execute($workspace, $user, [
                'content' => data_get($payload, 'content') ?? '',
                'created_via' => $createdVia,
            ]);

            $postPlatformIds = $post->postPlatforms()->pluck('id', 'social_account_id');

            $composerPayload = [
                ...Arr::except($payload, 'platforms'),
                'platforms' => collect(data_get($payload, 'platforms', []))
                    ->map(fn (array $platform): array => [
                        ...Arr::except($platform, 'social_account_id'),
                        'id' => (string) $postPlatformIds->get(data_get($platform, 'social_account_id')),
                    ])
                    ->values()
                    ->all(),
            ];

            $validated = PostComposerValidator::make($post, $composerPayload)->validate();

            return UpdatePost::execute($workspace, $post, $validated);
        });
    }
}
