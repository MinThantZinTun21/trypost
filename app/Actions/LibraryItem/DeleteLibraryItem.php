<?php

declare(strict_types=1);

namespace App\Actions\LibraryItem;

use App\Models\LibraryItem;

class DeleteLibraryItem
{
    /**
     * Remove a Library item. Its media stay in the workspace's assets, because
     * Posts scheduled from it may still use them.
     */
    public static function execute(LibraryItem $libraryItem): void
    {
        $libraryItem->delete();
    }
}
