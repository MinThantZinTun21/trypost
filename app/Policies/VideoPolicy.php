<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Video;
use Illuminate\Auth\Access\Response;

class VideoPolicy
{
    /**
     * A Video in another workspace denies as 404, like a Post.
     */
    public function delete(User $user, Video $video): bool|Response
    {
        if ($video->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return true;
    }
}
