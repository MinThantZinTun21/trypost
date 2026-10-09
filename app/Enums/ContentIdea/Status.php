<?php

declare(strict_types=1);

namespace App\Enums\ContentIdea;

enum Status: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
