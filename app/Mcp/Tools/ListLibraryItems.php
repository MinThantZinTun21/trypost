<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\HandlesLibraryItems;
use App\Models\LibraryItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_library_items')]
#[Description('Lists the Owner\'s Content library, newest first: ready-made content, each with its media, Facebook text, TikTok caption, YouTube title, description and tags, and how many Posts were made from it. Returns the last 10 unless you pass count (1 to 50).')]
#[IsReadOnly]
class ListLibraryItems extends Tool
{
    use HandlesLibraryItems;

    private const int DEFAULT_COUNT = 10;

    private const int MAX_COUNT = 50;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'count' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_COUNT],
        ]);

        $libraryItems = $request->user()->resolveCurrentWorkspace()->libraryItems()
            ->withCount('posts')
            ->newestFirst()
            ->limit((int) (data_get($validated, 'count') ?? self::DEFAULT_COUNT))
            ->get();

        return Response::structured([
            'library_items' => $libraryItems
                ->map(fn (LibraryItem $libraryItem): array => $this->libraryItemPayload($libraryItem))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'count' => $schema->integer()->min(1)->max(self::MAX_COUNT)->default(self::DEFAULT_COUNT)->description('How many of the latest items to return. Defaults to 10.'),
        ];
    }
}
