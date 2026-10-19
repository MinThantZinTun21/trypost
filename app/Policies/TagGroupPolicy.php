<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TagGroupPolicy
{
    /**
     * A Tag group in another workspace denies as 404, like a Task.
     */
    public function manage(User $user, TagGroup $tagGroup): bool|Response
    {
        if ($tagGroup->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return true;
    }
}
