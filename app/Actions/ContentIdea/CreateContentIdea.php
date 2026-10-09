<?php

declare(strict_types=1);

namespace App\Actions\ContentIdea;

use App\Enums\ContentIdea\Status;
use App\Enums\Post\CreatedVia;
use App\Models\ContentIdea;
use App\Models\Workspace;

class CreateContentIdea
{
    /**
     * Create a Content idea, which always starts as New.
     *
     * @param  array{title: string, details?: ?string}  $data
     */
    public static function execute(Workspace $workspace, array $data, CreatedVia $createdVia): ContentIdea
    {
        return $workspace->contentIdeas()->create([
            'title' => data_get($data, 'title'),
            'details' => data_get($data, 'details'),
            'status' => Status::New,
            'created_via' => $createdVia,
        ]);
    }
}
