<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Dto\MediaItem;
use App\Models\PostPlatform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_post')]
#[Description('Gets one of the Owner\'s Posts: its content, media, scheduled time, and for each Social account the Content type, status, error message and live link once published.')]
#[IsReadOnly]
class GetPost extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string', 'uuid']]);

        $post = $request->user()->resolveCurrentWorkspace()
            ->posts()
            ->with(['postPlatforms' => fn ($query) => $query->enabled()->with('socialAccount')])
            ->find(data_get($validated, 'id'));

        if ($post === null) {
            return Response::error(__('mcp.post.not_found'));
        }

        return Response::structured([
            'post' => [
                'id' => $post->id,
                'status' => $post->status->value,
                'content' => $post->content,
                'media' => $post->mediaItems->map(fn (MediaItem $media): array => [
                    'id' => $media->id,
                    'type' => $media->kind()?->value,
                    'url' => $media->url,
                ])->values()->all(),
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                'published_at' => $post->published_at?->toIso8601String(),
                'social_accounts' => $post->postPlatforms->map(fn (PostPlatform $postPlatform): array => [
                    'social_account_id' => $postPlatform->social_account_id,
                    'platform' => $postPlatform->platform->value,
                    'display_name' => $postPlatform->display_name,
                    'content_type' => $postPlatform->content_type?->value,
                    'status' => $postPlatform->status->value,
                    'error_message' => $postPlatform->error_message,
                    'url' => $postPlatform->platform_url,
                    'published_at' => $postPlatform->published_at?->toIso8601String(),
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The Post\'s id, from list_posts or create_post.')->required(),
        ];
    }
}
