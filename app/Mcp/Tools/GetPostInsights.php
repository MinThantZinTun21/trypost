<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\SocialAccount\Platform;
use App\Http\Resources\App\PostInsightResource;
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

#[Name('get_post_insights')]
#[Description('Gets Post insights for one of the Owner\'s Posts, for each Facebook Page it published to: lifetime views, reach, reactions and clicks, plus video views, average and total watch time (milliseconds) and Reel plays for videos. Reads what the app stored from Facebook (daily for 30 days after publishing); insights is null until the first read. Comment and share counts are not available.')]
#[IsReadOnly]
class GetPostInsights extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['post_id' => ['required', 'string', 'uuid']]);

        $post = $request->user()->resolveCurrentWorkspace()
            ->posts()
            ->with(['postPlatforms' => fn ($query) => $query->enabled()->where('platform', Platform::Facebook)->with(['insight', 'socialAccount'])])
            ->find(data_get($validated, 'post_id'));

        if ($post === null) {
            return Response::error(__('mcp.post.not_found'));
        }

        return Response::structured([
            'post_id' => $post->id,
            'social_accounts' => $post->postPlatforms->map(fn (PostPlatform $postPlatform): array => [
                'social_account_id' => $postPlatform->social_account_id,
                'display_name' => $postPlatform->display_name,
                'content_type' => $postPlatform->content_type?->value,
                'status' => $postPlatform->status->value,
                'url' => $postPlatform->platform_url,
                'insights' => $postPlatform->insight === null ? null : (new PostInsightResource($postPlatform->insight))->resolve(),
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->string()->description('The Post\'s id, from list_posts or create_post.')->required(),
        ];
    }
}
