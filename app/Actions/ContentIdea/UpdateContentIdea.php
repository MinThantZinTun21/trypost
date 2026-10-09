<?php

declare(strict_types=1);

namespace App\Actions\ContentIdea;

use App\Models\ContentIdea;

class UpdateContentIdea
{
    /**
     * Change a Content idea's title and details. Its status moves through
     * ChangeContentIdeaStatus instead.
     *
     * @param  array{title: string, details?: ?string}  $data
     */
    public static function execute(ContentIdea $contentIdea, array $data): ContentIdea
    {
        $contentIdea->update([
            'title' => data_get($data, 'title'),
            'details' => data_get($data, 'details'),
        ]);

        return $contentIdea;
    }
}
