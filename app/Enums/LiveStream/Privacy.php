<?php

declare(strict_types=1);

namespace App\Enums\LiveStream;

/**
 * Who can watch a Live stream's broadcast on YouTube.
 */
enum Privacy: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';
}
