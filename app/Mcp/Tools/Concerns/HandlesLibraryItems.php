<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use App\Models\LibraryItem;
use Laravel\Mcp\Request;

/**
 * What every Library item tool shares: finding an item in the Owner's
 * workspace, and the `library_item` object they all return.
 */
trait HandlesLibraryItems
{
    protected function findLibraryItem(Request $request, string $id): ?LibraryItem
    {
        return $request->user()->resolveCurrentWorkspace()->libraryItems()->withCount('posts')->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function libraryItemPayload(LibraryItem $libraryItem): array
    {
        return [
            'id' => $libraryItem->id,
            'name' => $libraryItem->name,
            'media' => collect($libraryItem->media ?? [])
                ->map(fn (array $item): array => [
                    'id' => data_get($item, 'id'),
                    'type' => data_get($item, 'type'),
                    'url' => data_get($item, 'url'),
                    'original_filename' => data_get($item, 'original_filename'),
                ])
                ->all(),
            'facebook_text' => $libraryItem->facebook_text,
            'tiktok_caption' => $libraryItem->tiktok_caption,
            'youtube_title' => $libraryItem->youtube_title,
            'youtube_description' => $libraryItem->youtube_description,
            'youtube_tags' => $libraryItem->youtube_tags ?? [],
            'posts_count' => (int) ($libraryItem->posts_count ?? $libraryItem->posts()->count()),
            'created_via' => $libraryItem->created_via->value,
            'created_at' => $libraryItem->created_at?->toIso8601String(),
            'updated_at' => $libraryItem->updated_at?->toIso8601String(),
        ];
    }
}
