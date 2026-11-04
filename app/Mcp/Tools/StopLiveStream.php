<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\LiveStream\StopLiveStream as StopLiveStreamAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('stop_live_stream')]
#[Description('Stops a Live stream: a live one ends on YouTube within a few seconds and keeps its recording; a scheduled one is cancelled and its Upcoming broadcast removed. Ask the Owner first.')]
class StopLiveStream extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string', 'uuid']]);

        $liveStream = $request->user()->resolveCurrentWorkspace()->liveStreams()->find(data_get($validated, 'id'));

        if ($liveStream === null) {
            return Response::error(__('mcp.live_stream.not_found'));
        }

        return Response::structured([
            'live_stream' => ListLiveStreams::summary(StopLiveStreamAction::execute($liveStream)->load('video')),
        ]);
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
