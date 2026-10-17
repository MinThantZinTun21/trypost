<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\LibraryItem\DeleteLibraryItem as DeleteLibraryItemAction;
use App\Mcp\Tools\Concerns\HandlesLibraryItems;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_library_item')]
#[Description('Deletes one Library item from the Owner\'s Content library. The Posts made from it and its media stay; the Posts only lose the link back to it. Ask the Owner before deleting.')]
#[IsDestructive]
class DeleteLibraryItem extends Tool
{
    use HandlesLibraryItems;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string', 'uuid']]);

        $libraryItem = $this->findLibraryItem($request, (string) data_get($validated, 'id'));

        if ($libraryItem === null) {
            return Response::error(__('mcp.library_item.not_found'));
        }

        DeleteLibraryItemAction::execute($libraryItem);

        return Response::structured([
            'deleted' => true,
            'id' => $libraryItem->id,
            'posts_kept' => (int) $libraryItem->posts_count,
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
