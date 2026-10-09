<?php

declare(strict_types=1);

namespace App\Actions\ContentIdea;

use App\Models\ContentIdea;

class DeleteContentIdea
{
    public static function execute(ContentIdea $contentIdea): void
    {
        $contentIdea->delete();
    }
}
