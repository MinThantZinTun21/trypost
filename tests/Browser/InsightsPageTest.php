<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\PageInsightSnapshot;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

/**
 * Wait for a data-testid element to mount and lay out. Assertions do not
 * auto-wait on SPA paint, and a blocking sleep() would starve the asset server.
 */
function waitForInsightsTestId(mixed $page, string $testId): void
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

function insightsOwner(): User
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

test('the insights page shows Page insights without javascript errors', function () {
    $user = insightsOwner();
    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $user->current_workspace_id,
        'insights_read_at' => now()->subHour(),
    ]);
    PageInsightSnapshot::factory()->create(['social_account_id' => $account->id, 'date' => now()->subDays(2)->toDateString()]);

    $this->actingAs($user);

    $page = visit(route('app.insights.index'));

    waitForInsightsTestId($page, 'insights-metric-views');

    $page->assertVisible('@insights-metric-followers')
        ->assertVisible('@insights-metric-views')
        ->assertVisible('@insights-range-7')
        ->assertVisible('@insights-read-at')
        ->assertNoJavaScriptErrors();
});

test('the insights page shows an empty state without a Facebook Page', function () {
    $this->actingAs(insightsOwner());

    $page = visit(route('app.insights.index'));

    waitForInsightsTestId($page, 'insights-empty');

    $page->assertVisible('@insights-empty')
        ->assertNoJavaScriptErrors();
});
