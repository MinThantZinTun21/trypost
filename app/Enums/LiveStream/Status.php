<?php

declare(strict_types=1);

namespace App\Enums\LiveStream;

/**
 * Where a Live stream stands (ADR 0017). Stopped and Failed are final.
 */
enum Status: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Stopped = 'stopped';
    case Failed = 'failed';

    /**
     * The states a Live stream can still be Stopped from.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Scheduled, self::Live];
    }
}
