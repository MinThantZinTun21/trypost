<?php

declare(strict_types=1);

namespace App\Enums\Task;

/**
 * A Pomodoro runs for its planned length; a free timer runs until stopped.
 */
enum TimerMode: string
{
    case Pomodoro = 'pomodoro';
    case Free = 'free';
}
