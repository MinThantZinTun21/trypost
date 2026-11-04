<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\LiveStream;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_live_streams')]
#[Description('Lists the Owner\'s Live streams, newest first, one page per call: each one\'s status (scheduled, live, stopped or failed), title, Video, YouTube channel, times and watch link.')]
#[IsReadOnly]
class ListLiveStreams extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $liveStreams = $request->user()->resolveCurrentWorkspace()
            ->liveStreams()
            ->with('video')
            ->latest()
            ->latest('id')
            ->paginate((int) config('app.pagination.default'), page: (int) data_get($validated, 'page', 1));

        return Response::structured([
            'live_streams' => $liveStreams->getCollection()->map(fn (LiveStream $liveStream): array => self::summary($liveStream))->values()->all(),
            'page' => $liveStreams->currentPage(),
            'last_page' => $liveStreams->lastPage(),
            'total' => $liveStreams->total(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(LiveStream $liveStream): array
    {
        return [
            'id' => $liveStream->id,
            'status' => $liveStream->status->value,
            'title' => $liveStream->title,
            'description' => $liveStream->description,
            'privacy' => $liveStream->privacy->value,
            'video' => $liveStream->video === null ? null : ['id' => $liveStream->video->id, 'name' => $liveStream->video->name],
            'social_account_id' => $liveStream->social_account_id,
            'starts_at' => $liveStream->starts_at->toIso8601String(),
            'stops_at' => $liveStream->stops_at?->toIso8601String(),
            'started_at' => $liveStream->started_at?->toIso8601String(),
            'ended_at' => $liveStream->ended_at?->toIso8601String(),
            'failure_reason' => $liveStream->failure_reason,
            'watch_url' => $liveStream->watchUrl(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->description('The page to fetch, starting at 1.'),
        ];
    }
}
