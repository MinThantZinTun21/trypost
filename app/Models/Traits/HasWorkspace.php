<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasWorkspace
{
    /**
     * Get all workspaces the user belongs to.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'user_workspace')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the user's current workspace.
     */
    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    /**
     * Make the given workspace the user's current one.
     */
    public function switchWorkspace(Workspace $workspace): void
    {
        $this->update(['current_workspace_id' => $workspace->id]);
    }

    /**
     * Workspaces the user can use on their current account (never cross-account).
     *
     * @return BelongsToMany<Workspace, $this>
     */
    public function accountWorkspaces(): BelongsToMany
    {
        return $this->workspaces()
            ->where('workspaces.account_id', $this->account_id);
    }

    /**
     * The Owner has a single workspace. When `current_workspace_id` is unset
     * (or points at a row that no longer exists), fall back to that workspace
     * and remember it as the current one.
     */
    public function resolveCurrentWorkspace(): ?Workspace
    {
        if ($this->currentWorkspace) {
            return $this->currentWorkspace;
        }

        $workspace = $this->accountWorkspaces()->oldest('workspaces.created_at')->first()
            ?? Workspace::query()->where('user_id', $this->id)->oldest()->first();

        if (! $workspace) {
            return null;
        }

        $this->switchWorkspace($workspace);
        $this->setRelation('currentWorkspace', $workspace);

        return $workspace;
    }
}
