<?php

declare(strict_types=1);

namespace App\Enums\Task;

/**
 * How a Timer session ended: a Pomodoro that ran its length or a free timer
 * the Owner stopped is Completed; a Pomodoro cut short, or any session
 * stopped by marking the Task Done, is Stopped.
 */
enum TimerOutcome: string
{
    case Completed = 'completed';
    case Stopped = 'stopped';
}
