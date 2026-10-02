<?php

declare(strict_types=1);

namespace App\Enums\Media;

/**
 * Origin of a media attachment on a post. `null`/absent means "uploaded by
 * the user" (legacy/unknown).
 */
enum Source: string
{
    case Unsplash = 'unsplash';
    case Giphy = 'giphy';
}
