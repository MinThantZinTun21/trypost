<?php

declare(strict_types=1);

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

test('the sidebar menu shows the Owner account links and no workspace switcher', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user);

    $page = visit(route('app.calendar'));

    waitForSidebarTestId($page, 'sidebar-user-menu');

    $page->assertVisible('@sidebar-user-menu')
        ->click('@sidebar-user-menu');

    waitForSidebarTestId($page, 'logout-button');

    $page->assertVisible('@sidebar-menu-my-account')
        ->assertMissing('@sidebar-menu-workspace-settings')
        ->assertMissing('@sidebar-create-workspace')
        ->assertVisible('@logout-button')
        ->assertNoJavaScriptErrors();
});
