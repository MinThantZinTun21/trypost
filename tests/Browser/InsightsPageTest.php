<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\PageInsightSnapshot;
use App\Models\Post;
use App\Models\PostInsight;
use App\Models\PostPlatform;
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

    waitForInsightsTestId($page, 'insights-chart');

    $page->assertVisible('@insights-metric-followers')
        ->assertVisible('@insights-metric-views')
        ->assertVisible('@insights-chart')
        ->assertVisible('@insights-top-posts')
        ->click('@insights-chart-metric-reach')
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

test('the insights page shows the last read error and the refresh button', function () {
    $user = insightsOwner();
    SocialAccount::factory()->facebook()->create([
        'workspace_id' => $user->current_workspace_id,
        'insights_error' => '(#10) Not enough permission',
    ]);

    $this->actingAs($user);

    $page = visit(route('app.insights.index'));

    waitForInsightsTestId($page, 'insights-error');

    $page->assertVisible('@insights-error')
        ->assertVisible('@insights-refresh')
        ->assertVisible('@insights-not-read')
        ->assertNoJavaScriptErrors();
});

test('the insights page disables refresh for a Page that is not connected', function () {
    $user = insightsOwner();
    SocialAccount::factory()->facebook()->tokenExpired()->create(['workspace_id' => $user->current_workspace_id]);

    $this->actingAs($user);

    $page = visit(route('app.insights.index'));

    waitForInsightsTestId($page, 'insights-disconnected');

    $page->assertVisible('@insights-disconnected')
        ->assertDisabled('@insights-refresh')
        ->assertNoJavaScriptErrors();
});

test('the Post page shows Post insights for a Facebook Reel', function () {
    $user = insightsOwner();
    $post = Post::factory()->published()->create(['workspace_id' => $user->current_workspace_id, 'user_id' => $user->id, 'content' => 'Desk tour']);
    $postPlatform = PostPlatform::factory()->facebookReel()->published()->create(['post_id' => $post->id]);
    PostInsight::factory()->create(['post_platform_id' => $postPlatform->id, 'views' => 1234, 'avg_watch_time_ms' => 5400]);

    $this->actingAs($user);

    $page = visit(route('app.posts.show', $post));

    waitForInsightsTestId($page, 'post-insights-views');

    $page->assertVisible('@post-insights-views')
        ->assertVisible('@post-insights-avg_watch_time_ms')
        ->assertNoJavaScriptErrors();
});
