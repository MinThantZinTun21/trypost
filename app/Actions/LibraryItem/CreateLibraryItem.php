<?php

declare(strict_types=1);

namespace App\Actions\LibraryItem;

use App\Enums\Post\CreatedVia;
use App\Models\LibraryItem;
use App\Models\Workspace;

class CreateLibraryItem
{
    /**
     * Save ready-made content to the Content library.
     *
     * @param  array{name: string, media_ids?: ?list<string>, facebook_text?: ?string, tiktok_caption?: ?string, youtube_title?: ?string, youtube_description?: ?string, youtube_tags?: ?list<string>}  $data
     */
    public static function execute(Workspace $workspace, array $data, CreatedVia $createdVia): LibraryItem
    {
        return $workspace->libraryItems()->create([
            ...LibraryItemAttributes::from($workspace, $data),
            'created_via' => $createdVia,
        ]);
    }
}
