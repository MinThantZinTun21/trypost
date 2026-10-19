<?php

declare(strict_types=1);

namespace App\Exceptions\Task;

use RuntimeException;

/**
 * Why a timer cannot start; the Mobile API answers it with a 409.
 */
class TimerRefusedException extends RuntimeException
{
    public static function alreadyRunning(): self
    {
        return new self(__('tasks.timer_already_running'));
    }

    public static function taskDone(): self
    {
        return new self(__('tasks.timer_task_done'));
    }
}
