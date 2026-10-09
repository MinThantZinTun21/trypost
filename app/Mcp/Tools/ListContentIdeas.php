<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\ContentIdea\Status;
use App\Mcp\Tools\Concerns\PresentsContentIdea;
use App\Models\ContentIdea;
use App\Support\ContentIdeaRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_content_ideas')]
#[Description('Pulls the Owner\'s latest Content ideas, newest first, each with its title, Markdown details and status. Returns the last 3 unless you pass count (1 to 50). Done ideas are included; pass status (new, in_progress or done) for only that status.')]
#[IsReadOnly]
class ListContentIdeas extends Tool
{
    use PresentsContentIdea;

    private const int DEFAULT_COUNT = 3;

    private const int MAX_COUNT = 50;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'count' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_COUNT],
            'status' => ['nullable', 'string', Rule::enum(Status::class)],
        ]);

        $query = $request->user()->resolveCurrentWorkspace()->contentIdeas();

        if ($status = Status::tryFrom((string) data_get($validated, 'status'))) {
            $query->where('status', $status);
        }

        $contentIdeas = $query->newestFirst()
            ->limit((int) data_get($validated, 'count', self::DEFAULT_COUNT))
            ->get();

        return Response::structured([
            'content_ideas' => $contentIdeas
                ->map(fn (ContentIdea $contentIdea): array => $this->contentIdeaPayload($contentIdea))
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
            'count' => $schema->integer()->min(1)->max(self::MAX_COUNT)->default(self::DEFAULT_COUNT)->description('How many of the latest ideas to return. Defaults to 3.'),
            'status' => $schema->string()->enum(ContentIdeaRules::statusValues())->description('Optional: only ideas with this status. Leave it out to include every status, Done too.'),
        ];
    }
}
