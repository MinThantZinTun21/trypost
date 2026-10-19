<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\Task\Status;
use App\Models\Task;
use App\Models\TimerSession;

class SetTaskStatus
{
    /**
     * Done stamps when the Task was finished, keeping the first stamp if it
     * was already Done, and stops its running timer; any other status clears
     * the stamp.
     */
    public static function execute(Task $task, Status $status): Task
    {
        if ($status === Status::Done) {
            $task->timerSessions()->running()->get()
                ->each(fn (TimerSession $session) => StopTimer::execute($session, isTaskDone: true));
        }

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
