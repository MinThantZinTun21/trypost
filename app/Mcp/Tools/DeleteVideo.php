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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_video')]
#[Description('Deletes one of the Owner\'s Videos and its file for good; its Download link stops working. Ask the Owner before deleting.')]
#[IsDestructive]
class DeleteVideo extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string', 'uuid']]);

        $video = $request->user()->resolveCurrentWorkspace()->videos()->find(data_get($validated, 'id'));

        if ($video === null) {
            return Response::error(__('mcp.video.not_found'));
        }

        $video->delete();

        return Response::structured([
            'deleted' => true,
            'id' => $video->id,
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
