<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

test('user can get workspaces they belong to', function () {
    $user = User::factory()->create();
    $workspace1 = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace2 = Workspace::factory()->create(['user_id' => $user->id]);

    // Add user as owner to both workspaces via pivot
    $workspace1->members()->attach($user->id, ['role' => Role::Member->value]);
    $workspace2->members()->attach($user->id, ['role' => Role::Member->value]);

    expect($user->workspaces)->toHaveCount(2);
    expect($user->workspaces->pluck('id')->toArray())->toContain($workspace1->id, $workspace2->id);
});

test('user can get workspaces as member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $workspace->members()->attach($member->id, ['role' => Role::Member->value]);

    expect($member->workspaces)->toHaveCount(1);
    expect($member->workspaces->first()->id)->toBe($workspace->id);
});

test('user can get current workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $user->update(['current_workspace_id' => $workspace->id]);

    expect($user->currentWorkspace->id)->toBe($workspace->id);
});

test('user can switch workspace', function () {
    $user = User::factory()->create();
    $workspace1 = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace2 = Workspace::factory()->create(['user_id' => $user->id]);
    $user->update(['current_workspace_id' => $workspace1->id]);

    $user->switchWorkspace($workspace2);

    expect($user->fresh()->current_workspace_id)->toBe($workspace2->id);
});

test('accountWorkspaces excludes memberships on other accounts', function () {
    $sharedOwner = User::factory()->create();
    $user = User::factory()->create();
    $personalAccountId = $user->account_id;

    $personalWorkspace = Workspace::factory()->create([
        'account_id' => $personalAccountId,
        'user_id' => $user->id,
    ]);
    $personalWorkspace->members()->attach($user->id, ['role' => Role::Admin->value]);

    $sharedWorkspace = Workspace::factory()->create([
        'account_id' => $sharedOwner->account_id,
        'user_id' => $sharedOwner->id,
    ]);
    $sharedWorkspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['account_id' => $sharedOwner->account_id]);

    expect($user->fresh()->accountWorkspaces()->pluck('workspaces.id')->all())
        ->toEqualCanonicalizing([$sharedWorkspace->id]);
});

test('resolveCurrentWorkspace keeps an existing current workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $user->update(['current_workspace_id' => $workspace->id]);

    expect($user->fresh()->resolveCurrentWorkspace()->id)->toBe($workspace->id);
});

test('resolveCurrentWorkspace falls back to the single workspace and remembers it', function () {
    $user = User::factory()->create(['current_workspace_id' => null]);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);

    expect($user->resolveCurrentWorkspace()->id)->toBe($workspace->id)
        ->and($user->fresh()->current_workspace_id)->toBe($workspace->id);
});

test('resolveCurrentWorkspace returns null when the user has no workspace', function () {
    $user = User::factory()->create(['current_workspace_id' => null]);

    expect($user->resolveCurrentWorkspace())->toBeNull();
});
