<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\Task\Status;
use App\Enums\Task\TimerMode;
use App\Exceptions\Task\TimerRefusedException;
use App\Models\Task;
use App\Models\TimerSession;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StartTimer
{
    /**
     * Starts a timer on the Task, moving a To do Task to In progress. Only
     * one session runs per workspace, checked under a lock so two quick taps
     * cannot both start one; a Pomodoro whose time ran out no longer counts.
     *
     * @throws TimerRefusedException
     */
    public static function execute(Task $task, TimerMode $mode, ?int $plannedSeconds): TimerSession
    {
        if ($task->status === Status::Done) {
            throw TimerRefusedException::taskDone();
        }

        try {
            return Cache::lock("task_timer:{$task->workspace_id}", 10)
                ->block(5, fn (): TimerSession => DB::transaction(function () use ($task, $mode, $plannedSeconds): TimerSession {
                    $isRunning = TimerSession::query()
                        ->where('workspace_id', $task->workspace_id)
                        ->running()
                        ->get()
                        ->contains(fn (TimerSession $session): bool => $session->finishIfElapsed()->isRunning());

                    if ($isRunning) {
                        throw TimerRefusedException::alreadyRunning();
                    }

                    if ($task->status === Status::Todo) {
                        SetTaskStatus::execute($task, Status::InProgress);
                    }

                    return $task->timerSessions()->create([
                        'workspace_id' => $task->workspace_id,
                        'mode' => $mode,
                        'started_at' => now(),
                        'planned_seconds' => $mode === TimerMode::Pomodoro ? $plannedSeconds : null,
                    ]);
                }));
        } catch (LockTimeoutException) {
            throw TimerRefusedException::alreadyRunning();
        }
    }
}
