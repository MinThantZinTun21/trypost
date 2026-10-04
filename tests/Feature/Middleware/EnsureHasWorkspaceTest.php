<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia;

test('an unset current workspace falls back to the Owner single workspace', function () {
    $user = User::factory()->create(['current_workspace_id' => null]);
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);

    $this->actingAs($user)
        ->get(route('app.calendar'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('posts/Calendar')
            ->where('auth.currentWorkspace.id', $workspace->id),
        );

    expect($user->fresh()->current_workspace_id)->toBe($workspace->id);
});

test('an owned workspace is found even without a membership row', function () {
    $user = User::factory()->create(['current_workspace_id' => null]);
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('app.posts.index'))
        ->assertOk();

    expect($user->fresh()->current_workspace_id)->toBe($workspace->id);
});

test('a user with no workspace gets one instead of a dead end', function () {
    $user = User::factory()->create(['current_workspace_id' => null]);

    $this->actingAs($user)
        ->get(route('app.calendar'))
        ->assertOk();

    $user->refresh();

    expect($user->current_workspace_id)->not->toBeNull()
        ->and($user->currentWorkspace->account_id)->toBe($user->account_id);
});

test('oauth callbacks are not blocked by the workspace gate and self-close the popup', function () {
    $user = User::factory()->create(['current_workspace_id' => null]);

    $this->actingAs($user)
        ->get(route('app.social.tiktok.callback'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounts/PopupCallback')
            ->where('success', false),
        );
});
