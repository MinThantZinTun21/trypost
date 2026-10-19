<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\Task\ReminderType;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SaveTask
{
    /**
     * Creates the Task, or replaces an existing one's fields, and gives it
     * exactly the given Task tags. Reminders replace the Task's set when
     * given; left out, its own move with the due.
     *
     * @param  array{title: ?string, description: ?string, priority: int, color: ?string, pinned: bool, due_at: mixed, due_all_day: bool}  $attributes
     * @param  list<string>  $taskTagIds
     * @param  list<array{type: ReminderType, remind_at: ?CarbonImmutable, minutes_before: ?int, repeat_until_dismissed: bool}>|null  $reminders
     */
    public static function execute(Workspace $workspace, User $user, ?Task $task, array $attributes, array $taskTagIds, ?array $reminders = null): Task
    {
        return DB::transaction(function () use ($workspace, $user, $task, $attributes, $taskTagIds, $reminders): Task {
            $task ??= $workspace->tasks()->make(['user_id' => $user->id]);

            $task->fill([
                'title' => data_get($attributes, 'title'),
                'description' => data_get($attributes, 'description'),
                'priority' => data_get($attributes, 'priority'),
                'color' => data_get($attributes, 'color'),
                'pinned' => data_get($attributes, 'pinned', false),
                'due_at' => data_get($attributes, 'due_at'),
                'due_all_day' => data_get($attributes, 'due_all_day'),
            ])->save();

            $task->taskTags()->sync($taskTagIds);
            SyncTaskReminders::execute($task, $reminders);

            return $task;
        });
    }
}
