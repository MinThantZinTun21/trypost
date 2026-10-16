<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

/**
 * Wait for a data-testid element to mount and lay out; see AccountsPageTest.
 */
function waitForCalendarTestId(mixed $page, string $testId): void
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

/**
 * The Owner with one post scheduled today to a Facebook Page and a TikTok account.
 */
function calendarOwnerWithScheduledPost(): User
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => 'Launch day',
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now('UTC')->setTime(12, 0),
    ]);

    foreach ([['facebook', 'Lucky Studio', 'luckystudio'], ['tiktok', 'Lucky Clips', 'luckyclips']] as [$platform, $name, $username]) {
        PostPlatform::factory()->{$platform}()->create([
            'post_id' => $post->id,
            'social_account_id' => SocialAccount::factory()->{$platform}()->create([
                'workspace_id' => $workspace->id,
                'display_name' => $name,
                'username' => $username,
            ])->id,
            'enabled' => true,
        ]);
    }

    return $user->fresh();
}

test('the week view names the social accounts a post publishes to', function () {
    $this->actingAs(calendarOwnerWithScheduledPost());

    $page = visit(route('app.calendar', ['view' => 'week']));

    waitForCalendarTestId($page, 'calendar-post-accounts');

    $page->assertSeeIn('@calendar-post-accounts', 'Lucky Studio')
        ->assertSeeIn('@calendar-post-accounts', 'Lucky Clips')
        ->assertNoJavaScriptErrors();
});

test('the day view names each social account with its handle', function () {
    $this->actingAs(calendarOwnerWithScheduledPost());

    $page = visit(route('app.calendar', ['view' => 'day']));

    waitForCalendarTestId($page, 'calendar-post-accounts');

    $page->assertSeeIn('@calendar-post-accounts', 'Lucky Studio')
        ->assertSeeIn('@calendar-post-accounts', '@luckystudio')
        ->assertSeeIn('@calendar-post-accounts', 'Lucky Clips')
        ->assertNoJavaScriptErrors();
});

/**
 * The Owner from calendarOwnerWithScheduledPost() with a second post today that
 * publishes only to the TikTok account.
 */
function calendarOwnerWithTikTokOnlyPost(): User
{
    $user = calendarOwnerWithScheduledPost();
    $tiktok = $user->currentWorkspace->socialAccounts()->where('username', 'luckyclips')->firstOrFail();

    $post = Post::factory()->create([
        'workspace_id' => $user->current_workspace_id,
        'user_id' => $user->id,
        'content' => 'Clips only',
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now('UTC')->setTime(15, 0),
    ]);
    PostPlatform::factory()->tiktok()->create([
        'post_id' => $post->id,
        'social_account_id' => $tiktok->id,
        'enabled' => true,
    ]);

    return $user;
}

test('the account filter shows only the posts for the chosen social account', function () {
    $user = calendarOwnerWithTikTokOnlyPost();
    $facebook = $user->currentWorkspace->socialAccounts()->where('username', 'luckystudio')->firstOrFail();
    $this->actingAs($user);

    $page = visit(route('app.calendar', ['view' => 'day']));

    waitForCalendarTestId($page, 'calendar-account-filter');
    $page->assertSee('Clips only');

    $page->click('@calendar-account-filter');
    waitForCalendarTestId($page, "calendar-account-option-{$facebook->id}");
    $page->click("@calendar-account-option-{$facebook->id}");

    $page->script(<<<'JS'
        (async () => {
            for (let i = 0; i < 100; i++) {
                if (location.search.includes('account=')) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);
    waitForCalendarTestId($page, 'calendar-post-accounts');

    $page->assertSeeIn('@calendar-account-filter', 'Lucky Studio')
        ->assertSee('Launch day')
        ->assertDontSee('Clips only')
        ->assertNoJavaScriptErrors();
});

test('the account filter stays on when moving to the next day', function () {
    $user = calendarOwnerWithTikTokOnlyPost();
    $tiktok = $user->currentWorkspace->socialAccounts()->where('username', 'luckyclips')->firstOrFail();
    $this->actingAs($user);

    $page = visit(route('app.calendar', ['view' => 'day', 'account' => $tiktok->id]));

    waitForCalendarTestId($page, 'calendar-account-filter');
    $page->assertSeeIn('@calendar-account-filter', 'Lucky Clips')
        ->assertSee('Clips only');

    $page->click('@calendar-next');
    $page->script(<<<'JS'
        (async () => {
            for (let i = 0; i < 100; i++) {
                if (location.search.includes('day=')) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);

    expect($page->script('location.search'))->toContain("account={$tiktok->id}");
    $page->assertNoJavaScriptErrors();
});

test('each post shows its status and each social account its own', function (string $view) {
    $user = calendarOwnerWithScheduledPost();
    $post = Post::query()->sole();
    $post->update(['status' => PostStatus::PartiallyPublished]);
    $post->postPlatforms()->where('platform', 'facebook')->update(['status' => PlatformStatus::Published]);
    $post->postPlatforms()->where('platform', 'tiktok')->update(['status' => PlatformStatus::Failed]);
    $this->actingAs($user);

    $page = visit(route('app.calendar', ['view' => $view]));

    waitForCalendarTestId($page, 'calendar-post-status');

    expect($page->script('document.querySelector("[data-testid=calendar-post-status]").dataset.status'))
        ->toBe('partially_published')
        ->and($page->script('[...document.querySelectorAll("[data-testid=calendar-post-account-status]")].map((el) => el.dataset.status).sort()'))
        ->toBe(['failed', 'published']);
    $page->assertSeeIn('@calendar-post-status', __('posts.status.partially_published'))
        ->assertNoJavaScriptErrors();
})->with(['day', 'week']);

test('a scheduled post shows its accounts as pending', function () {
    $this->actingAs(calendarOwnerWithScheduledPost());

    $page = visit(route('app.calendar', ['view' => 'day']));

    waitForCalendarTestId($page, 'calendar-post-account-status');

    expect($page->script('[...document.querySelectorAll("[data-testid=calendar-post-account-status]")].map((el) => el.dataset.status)'))
        ->toBe(['pending', 'pending']);
    $page->assertSeeIn('@calendar-post-status', __('posts.status.scheduled'))
        ->assertNoJavaScriptErrors();
});

test('the month view marks each post with its status', function () {
    $user = calendarOwnerWithScheduledPost();
    Post::query()->sole()->update(['status' => PostStatus::Failed]);
    $this->actingAs($user);

    $page = visit(route('app.calendar', ['view' => 'month']));

    waitForCalendarTestId($page, 'calendar-post-status');

    expect($page->script('document.querySelector("[data-testid=calendar-post-status]").dataset.status'))->toBe('failed')
        ->and($page->script('document.querySelector("[data-testid=calendar-post-status]").getAttribute("title")'))->toBe(__('posts.status.failed'));
    $page->assertNoJavaScriptErrors();
});
