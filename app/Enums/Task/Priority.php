<?php

declare(strict_types=1);

namespace App\Enums\Task;

enum Priority: int
{
    case None = 0;
    case Low = 1;
    case Medium = 2;
    case High = 3;
}
