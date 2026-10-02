<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\BrowserTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

pest()->extend(BrowserTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Test Impact Analysis
|--------------------------------------------------------------------------
|
| Only re-run tests affected by local changes, replaying cached results for
| the rest. Scoped to local runs via "locally()" — automatically skipped on
| CI (or when the "--ci" flag is passed), which always runs the full suite.
|
*/

pest()->tia()->locally();

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Move a member onto a shared account (stranded-member / invitee fixture).
 *
 * @return array{
 *     owner: User,
 *     member: User,
 *     shared_workspaces: list<Workspace>
 * }
 */
function strandedMemberOnSharedAccount(
    int $sharedWorkspaces = 0,
    bool $attachMember = true,
    bool $attachMemberToAll = true,
    bool $setMemberCurrent = false,
    ?User $owner = null,
    ?string $memberEmail = null,
): array {
    $owner ??= User::factory()->create();
    $member = User::factory()->create(array_filter([
        'email' => $memberEmail,
    ]));

    // Closed-account model: the member's empty signup shell is gone after
    // accepting the invite, so drop it here to match the real state.
    $member->account?->delete();

    $shared = [];

    for ($i = 0; $i < $sharedWorkspaces; $i++) {
        $workspace = Workspace::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $owner->id,
        ]);

        $workspace->members()->syncWithoutDetaching([
            $owner->id => ['role' => Role::Admin->value],
        ]);

        if ($attachMember && ($attachMemberToAll || $i === 0)) {
            $workspace->members()->attach($member->id, [
                'role' => Role::Member->value,
            ]);
        }

        $shared[] = $workspace;
    }

    $member->update([
        'account_id' => $owner->account_id,
        'current_workspace_id' => ($setMemberCurrent && $shared !== [])
            ? $shared[0]->id
            : null,
    ]);

    return [
        'owner' => $owner->fresh(),
        'member' => $member->fresh(),
        'shared_workspaces' => $shared,
    ];
}
