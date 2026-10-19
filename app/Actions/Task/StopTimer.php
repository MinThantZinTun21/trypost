<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\Task\TimerMode;
use App\Enums\Task\TimerOutcome;
use App\Models\TimerSession;

class StopTimer
{
    /**
     * Ends a running session now. A Pomodoro that already ran its length is
     * Completed at its planned end; one cut short is Stopped. The Owner's Stop
     * completes a free timer; marking the Task Done stops it. An ended
     * session stays as it was.
     */
    public static function execute(TimerSession $session, bool $isTaskDone = false): TimerSession
    {
        if (! $session->finishIfElapsed()->isRunning()) {
            return $session;
        }

        $session->update([
            'ended_at' => now(),
            'outcome' => $session->mode === TimerMode::Free && ! $isTaskDone ? TimerOutcome::Completed : TimerOutcome::Stopped,
        ]);

        return $session;
    }
}
