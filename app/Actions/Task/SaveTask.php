<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class SaveTask
{
    /**
     * Creates the Task, or replaces an existing one's fields, and gives it
     * exactly the given Task tags.
     *
     * @param  array{title: string, description: ?string, priority: int, due_at: mixed, due_all_day: bool}  $attributes
     * @param  list<string>  $taskTagIds
     */
    public static function execute(Workspace $workspace, User $user, ?Task $task, array $attributes, array $taskTagIds): Task
    {
        return DB::transaction(function () use ($workspace, $user, $task, $attributes, $taskTagIds): Task {
            $task ??= $workspace->tasks()->make(['user_id' => $user->id]);

            $task->fill([
                'title' => data_get($attributes, 'title'),
                'description' => data_get($attributes, 'description'),
                'priority' => data_get($attributes, 'priority'),
                'due_at' => data_get($attributes, 'due_at'),
                'due_all_day' => data_get($attributes, 'due_all_day'),
            ])->save();

            $task->taskTags()->sync($taskTagIds);

            return $task;
        });
    }
}
