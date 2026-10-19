<?php

declare(strict_types=1);

namespace App\Enums\Task;

/**
 * The fixed palette a Tag group picks its colour from; the Mobile app maps
 * each name to its own light and dark tones.
 */
enum TagColor: string
{
    case Gray = 'gray';
    case Red = 'red';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Green = 'green';
    case Teal = 'teal';
    case Blue = 'blue';
    case Brown = 'brown';
}
