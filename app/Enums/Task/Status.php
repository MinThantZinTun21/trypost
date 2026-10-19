<?php

declare(strict_types=1);

namespace App\Enums\Task;

/**
 * Where a Task stands (ADR 0016). There is no Archived status: a Task the
 * Owner no longer wants is deleted.
 */
enum Status: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Todo, self::InProgress];
    }
}
