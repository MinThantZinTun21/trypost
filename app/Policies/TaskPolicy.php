<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    /**
     * A Task in another workspace denies as 404, like a Video.
     */
    public function manage(User $user, Task $task): bool|Response
    {
        if ($task->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return true;
    }
}
