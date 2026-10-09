<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use App\Models\ContentIdea;

/**
 * The `content_idea` object every Content idea tool returns, so they all
 * describe an idea the same way.
 */
trait PresentsContentIdea
{
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
