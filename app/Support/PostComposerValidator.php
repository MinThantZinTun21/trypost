<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Requests\App\Post\UpdatePostRequest;
use App\Models\Post;
use App\Models\PostPlatform;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;

/**
 * The composer's own checks for a Post update that does not come through the
 * composer's PUT route (the MCP tools, the mobile API), so every way in runs
 * the same rules, messages and after-hook as UpdatePostRequest.
 */
class PostComposerValidator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function make(Post $post, array $payload): ValidationValidator
    {
        $formRequest = UpdatePostRequest::create("/{$post->id}", 'PUT', $payload);
        $route = (new Route('PUT', '{post}', []))->bind($formRequest);
        $route->setParameter('post', $post);
        $formRequest->setRouteResolver(fn (): Route => $route);

        $validator = Validator::make($formRequest->all(), $formRequest->rules(), $formRequest->messages(), $formRequest->attributes());
        $formRequest->withValidator($validator);

        return $validator;
    }

    /**
     * The composer payload that keeps a Post as it is stored: its status,
     * content, media, time and every enabled Post platform with its Content
     * type and meta (the publish checks read the submitted meta). Callers
     * override only what they change; UpdatePost disables any Post platform
     * the payload leaves out, so none may be dropped.
     *
     * @return array{status: string, content: string|null, media: array<int, mixed>, scheduled_at: string|null, platforms: array<int, array{id: string, content_type: string|null, meta: array<string, mixed>}>}
     */
    public static function storedPayload(Post $post): array
    {
        return [
            'status' => $post->status->value,
            'content' => $post->content,
            'media' => $post->media ?? [],
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            'platforms' => $post->postPlatforms()
                ->enabled()
                ->inComposerOrder()
                ->get(['id', 'content_type', 'meta'])
                ->map(fn (PostPlatform $postPlatform): array => [
                    'id' => $postPlatform->id,
                    'content_type' => $postPlatform->content_type?->value,
                    'meta' => $postPlatform->meta ?? [],
                ])
                ->values()
                ->all(),
        ];
    }
}
