<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Post\DeletePost as DeletePostAction;
use App\Support\PostStatusRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_post')]
#[Description('Deletes one of the Owner\'s Posts that has not gone out: a scheduled Post or a failed one. A Post that is publishing or published cannot be deleted, because it is already live on its Social accounts. Ask the Owner before deleting.')]
#[IsDestructive]
class DeletePost extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string', 'uuid']]);

        $post = $request->user()->resolveCurrentWorkspace()->posts()->find(data_get($validated, 'id'));

        if ($post === null) {
            return Response::error(__('mcp.post.not_found'));
        }

        if (PostStatusRules::blocksDeletion($post)) {
            return Response::error(__('mcp.post.cannot_delete', ['status' => $post->status->value]));
        }

        DeletePostAction::execute($post);

        return Response::structured([
            'deleted' => true,
            'id' => $post->id,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The Post\'s id, from list_posts or create_post.')->required(),
        ];
    }
}
