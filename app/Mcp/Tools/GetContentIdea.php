<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\PresentsContentIdea;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_content_idea')]
#[Description('Gets one of the Owner\'s Content ideas in full: its title, Markdown details, status (new, in_progress or done) and link.')]
#[IsReadOnly]
class GetContentIdea extends Tool
{
    use PresentsContentIdea;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string', 'uuid']]);

        $contentIdea = $request->user()->resolveCurrentWorkspace()
            ->contentIdeas()
            ->find(data_get($validated, 'id'));

        if ($contentIdea === null) {
            return Response::error(__('mcp.content_idea.not_found'));
        }

        return Response::structured([
            'content_idea' => $this->contentIdeaPayload($contentIdea),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The Content idea\'s id, from list_content_ideas or create_content_idea.')->required(),
        ];
    }
}
