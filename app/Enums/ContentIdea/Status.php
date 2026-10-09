<?php

declare(strict_types=1);

namespace App\Enums\ContentIdea;

enum Status: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';
}
