<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\Task\Status;
use App\Models\Task;

class SetTaskStatus
{
    /**
     * Done stamps when the Task was finished, keeping the first stamp if it
     * was already Done; any other status clears it.
     */
    public static function execute(Task $task, Status $status): Task
    {
        $task->update([
            'status' => $status,
            'completed_at' => match (true) {
                $status !== Status::Done => null,
                $task->status === Status::Done => $task->completed_at,
                default => now(),
            },
        ]);

        return $task;
    }
}
