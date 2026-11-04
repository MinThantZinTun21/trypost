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

#[Name('get_live_stream')]
#[Description('Gets one of the Owner\'s Live streams: its status, title, Video, YouTube channel, times, failure reason and watch link.')]
#[IsReadOnly]
class GetLiveStream extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string', 'uuid']]);

        $liveStream = $request->user()->resolveCurrentWorkspace()->liveStreams()->with('video')->find(data_get($validated, 'id'));

        if ($liveStream === null) {
            return Response::error(__('mcp.live_stream.not_found'));
        }

        return Response::structured(['live_stream' => ListLiveStreams::summary($liveStream)]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The Live stream\'s id, from list_live_streams.')->required(),
        ];
    }
}
