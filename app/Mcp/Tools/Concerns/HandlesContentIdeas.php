<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use App\Models\ContentIdea;
use Laravel\Mcp\Request;

/**
 * What every Content idea tool shares: finding an idea in the Owner's
 * workspace, and the `content_idea` object they all return.
 */
trait HandlesContentIdeas
{
    /**
     * @return array{id: array<int, string>}
     */
    protected function contentIdeaIdRules(): array
    {
        return ['id' => ['required', 'string', 'uuid']];
    }

    protected function findContentIdea(Request $request, string $id): ?ContentIdea
    {
        return $request->user()->resolveCurrentWorkspace()->contentIdeas()->find($id);
    }

    /**
     * @return array{id: string, title: string, details: ?string, status: string, created_via: string, created_at: ?string, updated_at: ?string, url: string}
     */
    protected function contentIdeaPayload(ContentIdea $contentIdea): array
    {
        return [
            'id' => $contentIdea->id,
            'title' => $contentIdea->title,
            'details' => $contentIdea->details,
            'status' => $contentIdea->status->value,
            'created_via' => $contentIdea->created_via->value,
            'created_at' => $contentIdea->created_at?->toIso8601String(),
            'updated_at' => $contentIdea->updated_at?->toIso8601String(),
            'url' => route('app.ideas.show', $contentIdea),
        ];
    }
}
