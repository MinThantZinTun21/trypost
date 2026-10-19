<?php

declare(strict_types=1);

namespace App\Enums\Task;

/**
 * When a Reminder rings: at a fixed time, or a number of minutes before the
 * Task's due, moving with it.
 */
enum ReminderType: string
{
    case At = 'at';
    case BeforeDue = 'before_due';
}
