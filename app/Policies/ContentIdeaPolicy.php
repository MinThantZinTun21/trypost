<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContentIdea;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A Content idea must live in the user's current workspace; anything else
 * denies as 404 so ideas never leak across workspaces.
 */
class ContentIdeaPolicy
{
    public function view(User $user, ContentIdea $contentIdea): bool|Response
    {
        if ($contentIdea->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return true;
    }

    public function update(User $user, ContentIdea $contentIdea): bool|Response
    {
        if ($contentIdea->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('manageContentIdeas', $user->currentWorkspace);
    }

    public function delete(User $user, ContentIdea $contentIdea): bool|Response
    {
        if ($contentIdea->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('manageContentIdeas', $user->currentWorkspace);
    }
}
