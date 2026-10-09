<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\ContentIdea\ChangeContentIdeaStatus;
use App\Enums\ContentIdea\Status;
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

#[Name('update_content_idea_status')]
#[Description('Moves one of the Owner\'s Content ideas to new, in_progress or done. Mark it in_progress when you start on it and done once its Post is made.')]
class UpdateContentIdeaStatus extends Tool
{
    use HandlesContentIdeas;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            ...$this->contentIdeaIdRules(),
            ...ContentIdeaRules::status(),
        ]);

        $contentIdea = $this->findContentIdea($request, (string) data_get($validated, 'id'));

        if ($contentIdea === null) {
            return Response::error(__('mcp.content_idea.not_found'));
        }

        ChangeContentIdeaStatus::execute($contentIdea, Status::from((string) data_get($validated, 'status')));

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
            'status' => $schema->string()->enum(ContentIdeaRules::statusValues())->description('The new status.')->required(),
        ];
    }
}
