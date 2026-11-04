<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LiveStreamPolicy
{
    /**
     * A Live stream in another workspace denies as 404, like a Video.
     */
    public function stop(User $user, LiveStream $liveStream): bool|Response
    {
        if ($liveStream->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return true;
    }
}
