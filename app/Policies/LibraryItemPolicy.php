<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A Library item must live in the user's current workspace; anything else
 * denies as 404 so items never leak across workspaces.
 */
class LibraryItemPolicy
{
    public function view(User $user, LibraryItem $libraryItem): bool|Response
    {
        return $this->canManage($user, $libraryItem);
    }

    public function update(User $user, LibraryItem $libraryItem): bool|Response
    {
        return $this->canManage($user, $libraryItem);
    }

    public function delete(User $user, LibraryItem $libraryItem): bool|Response
    {
        return $this->canManage($user, $libraryItem);
    }

    /**
     * Schedule Posts from the item, which also needs the right to create Posts.
     */
    public function schedule(User $user, LibraryItem $libraryItem): bool|Response
    {
        $canManage = $this->canManage($user, $libraryItem);

        if ($canManage !== true) {
            return $canManage;
        }

        return $user->can('createPost', $user->currentWorkspace);
    }

    private function canManage(User $user, LibraryItem $libraryItem): bool|Response
    {
        if ($libraryItem->workspace_id !== $user->current_workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('manageLibrary', $user->currentWorkspace);
    }
}
