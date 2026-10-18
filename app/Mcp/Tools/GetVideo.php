<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_video')]
#[Description('Gets one of the Owner\'s Videos with a Download link: a permanent link that downloads the file without signing in (follow redirects, e.g. curl -L -o clip.mp4 "<download_url>"). The link stops working once the Video is deleted.')]
#[IsReadOnly]
class GetVideo extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string', 'uuid']]);

        $video = $request->user()->resolveCurrentWorkspace()->videos()->with('file')->find(data_get($validated, 'id'));

        if ($video === null || $video->file === null) {
            return Response::error(__('mcp.video.not_found'));
        }

        return Response::structured([
            'video' => [
                ...ListVideos::summary($video),
                'mime_type' => $video->file->mime_type,
                'download_url' => $video->downloadUrl(),
            ],
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The Video\'s id, from list_videos.')->required(),
        ];
    }
}
