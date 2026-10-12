<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\HandlesLibraryItems;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_library_item')]
#[Description('Gets one Library item from the Owner\'s Content library: its media, Facebook text, TikTok caption, YouTube title, description and tags, and how many Posts were made from it.')]
#[IsReadOnly]
class GetLibraryItem extends Tool
{
    use HandlesLibraryItems;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string', 'uuid']]);

        $libraryItem = $this->findLibraryItem($request, (string) data_get($validated, 'id'));

        if ($libraryItem === null) {
            return Response::error(__('mcp.library_item.not_found'));
        }

        return Response::structured([
            'library_item' => $this->libraryItemPayload($libraryItem),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The Library item\'s id, from list_library_items or create_library_item.')->required(),
        ];
    }
}
