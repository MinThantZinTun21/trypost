<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\ContentIdea\CreateContentIdea as CreateContentIdeaAction;
use App\Enums\Post\CreatedVia;
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

#[Name('create_content_idea')]
#[Description('Saves a Content idea for the Owner to post later: a title and optional Markdown details such as a brief, script or prompt. It starts as "new" and shows on the Owner\'s Content ideas page.')]
class CreateContentIdea extends Tool
{
    use HandlesContentIdeas;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate(ContentIdeaRules::content());

        $contentIdea = CreateContentIdeaAction::execute(
            $request->user()->resolveCurrentWorkspace(),
            $validated,
            CreatedVia::Mcp,
        );

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
            'title' => $schema->string()->max(ContentIdeaRules::TITLE_MAX)->description('A short name for the idea.')->required(),
            'details' => $schema->string()->max(ContentIdeaRules::DETAILS_MAX)->description('Optional Markdown: the brief, script, hook or prompt for the Post.'),
        ];
    }
}
