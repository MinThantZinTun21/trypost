<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

/**
 * Wait for a data-testid element to mount and lay out. Pest browser `@`
 * selectors resolve to data-testid, and assertions do not auto-wait on SPA paint.
 * Never `sleep()` here instead: the test server runs inside the PHP process, so
 * a blocking sleep starves the assets the page is trying to load.
 */
function waitForAccountsTestId(mixed $page, string $testId): void
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

function accountsOwner(): User
{
    $user = User::factory()->create();

    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

function accountsOwnerWithFacebook(): User
{
    $user = accountsOwner();

    SocialAccount::factory()->facebook()->create([
        'workspace_id' => $user->current_workspace_id,
        'platform_user_id' => 'fb-connected',
    ]);

    return $user;
}

test('a workspace without accounts lists every network with a connect slot', function () {
    $this->actingAs(accountsOwner());

    $page = visit(route('app.accounts'));

    waitForAccountsTestId($page, 'network-group-facebook');

    $page->assertVisible('@network-group-facebook')
        ->assertVisible('@connect-facebook')
        ->assertVisible('@connect-tiktok')
        ->assertMissing('@connect-another-facebook')
        ->assertMissing('@connect-account-button')
        ->assertNoJavaScriptErrors();
});

test('every network is listed, connected ones grouped with a slot for one more', function () {
    $user = accountsOwnerWithFacebook();

    SocialAccount::factory()->facebook()->create([
        'workspace_id' => $user->current_workspace_id,
        'platform_user_id' => 'fb-page',
    ]);

    $this->actingAs($user);

    $page = visit(route('app.accounts'));

    waitForAccountsTestId($page, 'network-group-facebook');

    $page->assertVisible('@network-group-facebook')
        ->assertVisible('@connect-another-facebook')
        ->assertMissing('@connect-facebook')
        ->assertVisible('@network-group-tiktok')
        ->assertVisible('@connect-tiktok')
        ->assertMissing('@connect-another-tiktok')
        ->assertNoJavaScriptErrors();
});

test('the accounts page has no header connect catalog', function () {
    $this->actingAs(accountsOwnerWithFacebook());

    $page = visit(route('app.accounts'));

    waitForAccountsTestId($page, 'network-group-facebook');

    $page->assertMissing('@connect-account-button')
        ->assertMissing('@connect-account-dialog')
        ->assertVisible('@connect-another-facebook')
        ->assertNoJavaScriptErrors();
});

test('a lost connection offers reconnect on the card and disconnect in its menu', function () {
    $user = accountsOwnerWithFacebook();

    $account = SocialAccount::factory()->tiktok()->tokenExpired()->create([
        'workspace_id' => $user->current_workspace_id,
        'platform_user_id' => 'tiktok-expired',
    ]);

    $this->actingAs($user);

    $page = visit(route('app.accounts'));

    waitForAccountsTestId($page, "reconnect-button-{$account->id}");

    $page->assertVisible("@reconnect-button-{$account->id}")
        ->click("@account-menu-{$account->id}");

    waitForAccountsTestId($page, "disconnect-{$account->id}");

    $page->assertVisible("@reconnect-{$account->id}")
        ->assertVisible("@disconnect-{$account->id}")
        ->assertNoJavaScriptErrors();
});
