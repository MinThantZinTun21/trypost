<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Video;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_videos')]
#[Description('Lists the Videos the Owner uploaded from their phone, newest first, one page per call: each one\'s name, size in bytes and duration in seconds when known. A Video is raw footage, not a Post; call get_video for its Download link.')]
#[IsReadOnly]
class ListVideos extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $videos = $request->user()->resolveCurrentWorkspace()
            ->videos()
            ->with('file')
            ->latest()
            ->latest('id')
            ->paginate((int) config('app.pagination.default'), page: (int) data_get($validated, 'page', 1));

        return Response::structured([
            'videos' => $videos->getCollection()->map(fn (Video $video): array => self::summary($video))->values()->all(),
            'page' => $videos->currentPage(),
            'last_page' => $videos->lastPage(),
            'total' => $videos->total(),
        ]);
    }

    /**
     * @return array{id: string, name: string, size: ?int, duration: mixed, created_at: ?string}
     */
    public static function summary(Video $video): array
    {
        return [
            'id' => $video->id,
            'name' => $video->name,
            'size' => $video->file?->size,
            'duration' => data_get($video->file?->meta, 'duration'),
            'created_at' => $video->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->description('Optional: the page to read, from 1.'),
        ];
    }
}
