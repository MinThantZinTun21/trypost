<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
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
