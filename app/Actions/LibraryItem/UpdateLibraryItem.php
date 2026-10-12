<?php

declare(strict_types=1);

namespace App\Actions\LibraryItem;

use App\Models\LibraryItem;

class UpdateLibraryItem
{
    /**
     * Replace a Library item's name, media and platform texts. Posts already
     * scheduled from it keep what they were created with.
     *
     * @param  array{name: string, media_ids?: ?list<string>, facebook_text?: ?string, tiktok_caption?: ?string, youtube_title?: ?string, youtube_description?: ?string, youtube_tags?: ?list<string>}  $data
     */
    public static function execute(LibraryItem $libraryItem, array $data): LibraryItem
    {
        $libraryItem->update(LibraryItemAttributes::from($libraryItem->workspace, $data));

        return $libraryItem;
    }
}
