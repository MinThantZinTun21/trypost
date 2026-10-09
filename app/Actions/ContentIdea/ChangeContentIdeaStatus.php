<?php

declare(strict_types=1);

namespace App\Actions\ContentIdea;

use App\Enums\ContentIdea\Status;
use App\Models\ContentIdea;

class ChangeContentIdeaStatus
{
    /**
     * Move a Content idea to any status. The web page and the Assistant both
     * change status through here.
     */
    public static function execute(ContentIdea $contentIdea, Status $status): ContentIdea
    {
        $contentIdea->update(['status' => $status]);

        return $contentIdea;
    }
}
