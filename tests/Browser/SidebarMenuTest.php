<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

/**
 * Wait for a data-testid element to mount and lay out. Pest browser assertions
 * do not auto-wait on SPA paint.
 */
function waitForSidebarTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            const sel = '[data-testid="{$testId}"]';
            for (let i = 0; i < 100; i++) {
                const el = document.querySelector(sel);
                if (el && el.getBoundingClientRect().height > 0) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);
}

test('account owners see workspace settings but no account billing in the sidebar menu', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user);

    $page = visit(route('app.calendar'));

    waitForSidebarTestId($page, 'sidebar-workspace-menu');

    $page->assertVisible('@sidebar-workspace-menu')
        ->click('@sidebar-workspace-menu');

    waitForSidebarTestId($page, 'logout-button');

    $page->assertVisible('@sidebar-menu-my-account')
        ->assertMissing('@sidebar-menu-account-settings')
        ->assertVisible('@sidebar-menu-workspace-settings')
        ->assertVisible('@logout-button');
});

test('workspace admins see workspace settings', function () {
    [
        'member' => $admin,
        'shared_workspaces' => [$workspace],
    ] = strandedMemberOnSharedAccount(
        sharedWorkspaces: 1,
        attachMember: true,
        setMemberCurrent: true,
    );

    $workspace->members()->updateExistingPivot($admin->id, [
        'role' => Role::Admin->value,
    ]);

    $this->actingAs($admin->fresh());

    $page = visit(route('app.calendar'));

    waitForSidebarTestId($page, 'sidebar-workspace-menu');

    $page->assertVisible('@sidebar-workspace-menu')
        ->click('@sidebar-workspace-menu');

    waitForSidebarTestId($page, 'logout-button');

    $page->assertVisible('@sidebar-menu-my-account')
        ->assertVisible('@sidebar-menu-workspace-settings')
        ->assertMissing('@sidebar-menu-account-settings')
        ->assertVisible('@logout-button');
});

test('workspace members do not see workspace settings in the sidebar menu', function () {
    [
        'member' => $member,
        'shared_workspaces' => [$workspace],
    ] = strandedMemberOnSharedAccount(
        sharedWorkspaces: 1,
        attachMember: true,
        setMemberCurrent: true,
    );

    $workspace->members()->updateExistingPivot($member->id, [
        'role' => Role::Member->value,
    ]);

    $this->actingAs($member->fresh());

    $page = visit(route('app.calendar'));

    waitForSidebarTestId($page, 'sidebar-workspace-menu');

    $page->assertVisible('@sidebar-workspace-menu')
        ->click('@sidebar-workspace-menu');

    waitForSidebarTestId($page, 'logout-button');

    $page->assertVisible('@sidebar-menu-my-account')
        ->assertMissing('@sidebar-menu-account-settings')
        ->assertMissing('@sidebar-menu-workspace-settings')
        ->assertVisible('@logout-button');
});

test('workspace viewers do not see workspace settings in the sidebar menu', function () {
    [
        'member' => $viewer,
        'shared_workspaces' => [$workspace],
    ] = strandedMemberOnSharedAccount(
        sharedWorkspaces: 1,
        attachMember: true,
        setMemberCurrent: true,
    );

    $workspace->members()->updateExistingPivot($viewer->id, [
        'role' => Role::Viewer->value,
    ]);

    $this->actingAs($viewer->fresh());

    $page = visit(route('app.calendar'));

    waitForSidebarTestId($page, 'sidebar-workspace-menu');

    $page->assertVisible('@sidebar-workspace-menu')
        ->click('@sidebar-workspace-menu');

    waitForSidebarTestId($page, 'logout-button');

    $page->assertVisible('@sidebar-menu-my-account')
        ->assertMissing('@sidebar-menu-account-settings')
        ->assertMissing('@sidebar-menu-workspace-settings')
        ->assertVisible('@logout-button');
});
