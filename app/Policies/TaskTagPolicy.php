<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaskTag;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskTagPolicy
{
    /**
     * A Task tag belongs to its Tag group's workspace.
     */
    public function manage(User $user, TaskTag $taskTag): bool|Response
    {
        return (new TagGroupPolicy)->manage($user, $taskTag->tagGroup);
    }
}
