<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\ContentIdea\DeleteContentIdea as DeleteContentIdeaAction;
use App\Mcp\Tools\Concerns\HandlesContentIdeas;
use App\Support\ContentIdeaRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_content_idea')]
#[Description('Deletes one of the Owner\'s Content ideas. To set an idea aside, mark it done with update_content_idea_status instead. Ask the Owner before deleting.')]
#[IsDestructive]
class DeleteContentIdea extends Tool
{
    use HandlesContentIdeas;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(ContentIdeaRules::id());

        $contentIdea = $this->findContentIdea($request, (string) data_get($validated, 'id'));

        if ($contentIdea === null) {
            return Response::error(__('mcp.content_idea.not_found'));
        }

        DeleteContentIdeaAction::execute($contentIdea);

        return Response::structured([
            'deleted' => true,
            'id' => $contentIdea->id,
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
