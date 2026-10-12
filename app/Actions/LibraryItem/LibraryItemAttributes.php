<?php

declare(strict_types=1);

namespace App\Actions\LibraryItem;

use App\Dto\MediaItem;
use App\Models\Media;
use App\Models\Workspace;
use App\Support\YouTubeTags;

class LibraryItemAttributes
{
    /**
     * The stored columns for validated Library item input: the media ids become
     * the same media items a Post stores, in the order given, and the Tags are
     * normalized the way they are sent to YouTube.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function from(Workspace $workspace, array $data): array
    {
        $assets = $workspace->getMedia('assets')->get()->keyBy('id');

        return [
            'name' => data_get($data, 'name'),
            'media' => collect(data_get($data, 'media_ids') ?? [])
                ->map(fn (string $id): ?Media => $assets->get($id))
                ->filter()
                ->map(fn (Media $media): array => MediaItem::fromMedia($media)->toArray())
                ->values()
                ->all(),
            'facebook_text' => self::text(data_get($data, 'facebook_text')),
            'tiktok_caption' => self::text(data_get($data, 'tiktok_caption')),
            'youtube_title' => self::text(data_get($data, 'youtube_title')),
            'youtube_description' => self::text(data_get($data, 'youtube_description')),
            'youtube_tags' => YouTubeTags::normalize(data_get($data, 'youtube_tags')),
        ];
    }

    private static function text(mixed $text): ?string
    {
        return is_string($text) && trim($text) !== '' ? $text : null;
    }
}
