<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

/**
 * There are no teams or roles: the Owner can do everything in their own
 * workspace, and nobody can touch a workspace on another account.
 */
class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $this->canAccess($user, $workspace);
    }

    public function manageAccounts(User $user, Workspace $workspace): bool
    {
        return $this->canAccess($user, $workspace);
    }

    public function createPost(User $user, Workspace $workspace): bool
    {
        return $this->canAccess($user, $workspace);
    }

    public function manageContentIdeas(User $user, Workspace $workspace): bool
    {
        return $this->canAccess($user, $workspace);
    }

    private function canAccess(User $user, Workspace $workspace): bool
    {
        if ($workspace->account_id !== $user->account_id) {
            return false;
        }

        if ($workspace->user_id === $user->id || $user->isAccountOwner()) {
            return true;
        }

        return $workspace->members()->where('user_id', $user->id)->exists();
    }
}
