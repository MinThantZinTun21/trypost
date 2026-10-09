<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Jobs\ReadFacebookInsights;
use App\Models\PageInsightSnapshot;
use App\Models\Post;
use App\Models\PostInsight;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->travelTo(now()->parse('2026-10-09 10:00:00'));
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

/**
 * @param  array<string, mixed>  $values
 */
function insightsSnapshot(SocialAccount $account, string $date, array $values): PageInsightSnapshot
{
    return PageInsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'date' => $date,
        'followers' => null,
        'new_follows' => null,
        'unfollows' => null,
        'views' => null,
        'reach' => null,
        'engagements' => null,
        'video_views' => null,
        ...$values,
    ]);
}

test('insights requires authentication', function () {
    $this->get(route('app.insights.index'))->assertRedirect(route('login'));
});

test('insights shows an empty state without a Facebook Page', function () {
    SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get(route('app.insights.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('insights/Index')
            ->has('accounts', 0)
            ->where('account', null)
            ->where('summary', null)
            ->where('range', 28)
            ->where('ranges', [7, 28, 90])
        );
});

test('insights selects the first Facebook Page and totals the range against the previous one', function () {
    $first = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id, 'created_at' => now()->subWeek()]);
    SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);

    // Current 7 days: 2026-10-02 .. 2026-10-08. Previous: 2026-09-25 .. 2026-10-01.
    insightsSnapshot($first, '2026-09-25', ['followers' => 90, 'views' => 5]);
    insightsSnapshot($first, '2026-10-01', ['followers' => 100, 'views' => 20, 'reach' => 7]);
    insightsSnapshot($first, '2026-10-02', ['followers' => 101, 'views' => 30, 'new_follows' => 2]);
    insightsSnapshot($first, '2026-10-08', ['followers' => 110, 'views' => 40, 'new_follows' => 3]);
    insightsSnapshot($first, '2026-10-09', ['followers' => 999, 'views' => 999]);

    $this->actingAs($this->user)
        ->get(route('app.insights.index', ['range' => 7]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('accounts', 2)
            ->where('account.id', $first->id)
            ->where('account.has_snapshots', true)
            ->where('range', 7)
            ->where('summary.from', '2026-10-02')
            ->where('summary.to', '2026-10-08')
            ->where('summary.metrics.0', ['key' => 'followers', 'current' => 110, 'previous' => 100])
            ->where('summary.metrics.1', ['key' => 'new_follows', 'current' => 5, 'previous' => null])
            ->where('summary.metrics.3', ['key' => 'views', 'current' => 70, 'previous' => 25])
            ->where('summary.metrics.4', ['key' => 'reach', 'current' => null, 'previous' => 7])
        );
});

test('the range ends at the newest stored day, never past the day Facebook finished counting', function (string $now, string $to) {
    $this->travelTo(now()->parse($now));
    $account = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    insightsSnapshot($account, '2026-10-07', ['views' => 10]);
    insightsSnapshot($account, '2026-10-08', ['views' => 20]);
    insightsSnapshot($account, '2026-10-09', ['views' => 999]);

    $this->actingAs($this->user)
        ->get(route('app.insights.index', ['range' => 7]))
        ->assertInertia(fn ($page) => $page->where('summary.to', $to));
})->with([
    'before Pacific midnight, the day before yesterday in UTC' => ['2026-10-10 02:00:00', '2026-10-08'],
    'after Pacific midnight, yesterday' => ['2026-10-10 10:00:00', '2026-10-09'],
]);

test('the range ends at the last stored day while the newest day is not read yet', function () {
    $account = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    insightsSnapshot($account, '2026-10-07', ['views' => 10]);

    $this->actingAs($this->user)
        ->get(route('app.insights.index', ['range' => 7]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.from', '2026-10-01')
            ->where('summary.to', '2026-10-07')
            ->where('summary.metrics.3.current', 10)
        );
});

test('insights shows the chosen Facebook Page', function () {
    SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id, 'created_at' => now()->subWeek()]);
    $second = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'insights_read_at' => now()->subHours(2),
        'insights_error' => 'Facebook said no',
    ]);

    $this->actingAs($this->user)
        ->get(route('app.insights.index', ['account' => $second->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('account.id', $second->id)
            ->where('account.read_at', now()->subHours(2)->toIso8601String())
            ->where('account.error', 'Facebook said no')
            ->where('account.has_snapshots', false)
        );
});

test('insights rejects a range it does not offer', function () {
    $this->actingAs($this->user)
        ->get(route('app.insights.index', ['range' => 30]))
        ->assertSessionHasErrors('range');
});

test('insights rejects a Social account that is not a Facebook Page in the workspace', function (Closure $makeAccount) {
    $account = $makeAccount($this->workspace);

    $this->actingAs($this->user)
        ->get(route('app.insights.index', ['account' => $account->id]))
        ->assertSessionHasErrors('account');
})->with([
    'another workspace' => [fn () => SocialAccount::factory()->facebook()->create()],
    'a TikTok account' => [fn (Workspace $workspace) => SocialAccount::factory()->tiktok()->create(['workspace_id' => $workspace->id])],
]);

test('insights lists every day in the range for the chart, with gaps as nulls', function () {
    $account = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    insightsSnapshot($account, '2026-10-03', ['views' => 12, 'reach' => 9]);
    insightsSnapshot($account, '2026-10-08', []);

    $this->actingAs($this->user)
        ->get(route('app.insights.index', ['range' => 7]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('summary.days', 7)
            ->where('summary.days.0.date', '2026-10-02')
            ->where('summary.days.0.views', null)
            ->where('summary.days.1', [
                'date' => '2026-10-03',
                'followers' => null,
                'new_follows' => null,
                'unfollows' => null,
                'views' => 12,
                'reach' => 9,
                'engagements' => null,
                'video_views' => null,
            ])
            ->where('summary.days.6.date', '2026-10-08')
        );
});

// Refresh now
test('refresh now queues a read and marks it pending', function () {
    Queue::fake();
    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'insights_read_at' => now()->subHours(2),
        'insights_attempted_at' => now()->subHours(2),
    ]);

    $this->actingAs($this->user)
        ->from(route('app.insights.index'))
        ->post(route('app.insights.refresh', $account))
        ->assertRedirect(route('app.insights.index'))
        ->assertSessionHasNoErrors();

    Queue::assertPushed(ReadFacebookInsights::class, fn (ReadFacebookInsights $job): bool => $job->account->is($account));

    $account->refresh();
    expect($account->insights_refresh_queued_at)->not->toBeNull()
        ->and($account->insightsRefreshPending())->toBeTrue();

    $this->actingAs($this->user)
        ->get(route('app.insights.index'))
        ->assertInertia(fn ($page) => $page->where('account.refresh_pending', true));
});

test('refresh now is refused within an hour of the last read, even one that failed', function (?string $readAt) {
    Queue::fake();
    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'insights_read_at' => $readAt,
        'insights_attempted_at' => now()->subMinutes(20),
        'insights_error' => $readAt === null ? 'Facebook answered HTTP 500.' : null,
    ]);

    $this->actingAs($this->user)
        ->post(route('app.insights.refresh', $account))
        ->assertSessionHasErrors(['refresh' => 'You can refresh again in 40 minutes.']);

    Queue::assertNothingPushed();

    $this->actingAs($this->user)
        ->get(route('app.insights.index'))
        ->assertInertia(fn ($page) => $page
            ->where('account.refresh_pending', false)
            ->where('account.refresh_available_at', now()->addMinutes(40)->toIso8601String())
        );
})->with([
    'a successful read' => [fn () => now()->subMinutes(20)->toDateTimeString()],
    'a failed read' => [null],
]);

test('refresh now is refused while a read is queued, until the mark goes stale', function () {
    Queue::fake();
    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'insights_refresh_queued_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($this->user)
        ->post(route('app.insights.refresh', $account))
        ->assertSessionHasErrors(['refresh' => 'A read is already running for this Page.']);

    $account->update(['insights_refresh_queued_at' => now()->subMinutes(16)]);

    $this->actingAs($this->user)
        ->post(route('app.insights.refresh', $account))
        ->assertSessionHasNoErrors();

    Queue::assertPushed(ReadFacebookInsights::class, 1);
});

test('refresh now is refused for a Page that is not connected', function (string $state) {
    Queue::fake();
    $account = SocialAccount::factory()->facebook()->{$state}()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get(route('app.insights.index'))
        ->assertInertia(fn ($page) => $page->where('account.connected', false));

    $this->actingAs($this->user)
        ->post(route('app.insights.refresh', $account))
        ->assertSessionHasErrors(['refresh' => 'Reconnect this Page on the Accounts page to read its Insights again.']);

    Queue::assertNothingPushed();
})->with(['disconnected', 'tokenExpired']);

test('refresh now is a 404 for another workspace or a non-Facebook account', function (Closure $makeAccount) {
    Queue::fake();
    $account = $makeAccount($this->workspace);

    $this->actingAs($this->user)
        ->post(route('app.insights.refresh', $account))
        ->assertNotFound();

    Queue::assertNothingPushed();
})->with([
    'another workspace' => [fn () => SocialAccount::factory()->facebook()->create()],
    'a YouTube account' => [fn (Workspace $workspace) => SocialAccount::factory()->youtube()->create(['workspace_id' => $workspace->id])],
]);

// Post insights
test('insights lists the top Posts published in the range by views', function () {
    $account = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    $makeInsight = function (string $content, string $publishedAt, int $views) use ($account): PostInsight {
        $post = Post::factory()->published()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id, 'content' => $content]);
        $postPlatform = PostPlatform::factory()->facebook()->published()->create([
            'post_id' => $post->id,
            'social_account_id' => $account->id,
            'published_at' => $publishedAt,
        ]);

        return PostInsight::factory()->create(['post_platform_id' => $postPlatform->id, 'views' => $views]);
    };

    $makeInsight('Quiet one', '2026-10-03 09:00:00', 40);
    $top = $makeInsight('Desk tour', '2026-10-08 20:00:00', 900);
    $makeInsight('Too old', '2026-09-20 09:00:00', 5000);
    $makeInsight('Before the range in Pacific time', '2026-10-02 05:00:00', 5000);
    $lateEvening = $makeInsight('Last evening in Pacific time', '2026-10-09 03:00:00', 500);
    $makeInsight('Published today', '2026-10-09 08:00:00', 5000);

    $this->actingAs($this->user)
        ->get(route('app.insights.index', ['range' => 7]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('topPosts', 3)
            ->where('topPosts.0.post_id', $top->postPlatform->post_id)
            ->where('topPosts.0.content', 'Desk tour')
            ->where('topPosts.0.insights.views', 900)
            ->where('topPosts.1.post_id', $lateEvening->postPlatform->post_id)
            ->where('topPosts.2.content', 'Quiet one')
        );
});

test('the Post page shows Post insights only where a Facebook Post platform has them', function () {
    $post = Post::factory()->published()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    $read = PostPlatform::factory()->facebookReel()->published()->create(['post_id' => $post->id]);
    PostInsight::factory()->create(['post_platform_id' => $read->id, 'views' => 321, 'reel_plays' => 200]);
    $unread = PostPlatform::factory()->facebook()->published()->create(['post_id' => $post->id]);
    $lookedUp = PostPlatform::factory()->facebookReel()->published()->create(['post_id' => $post->id]);
    PostInsight::factory()->create(['post_platform_id' => $lookedUp->id, 'feed_post_id' => '1_2', 'read_at' => null]);
    $story = PostPlatform::factory()->facebookStory()->published()->create(['post_id' => $post->id]);
    $tooOld = PostPlatform::factory()->facebook()->published()->create(['post_id' => $post->id, 'published_at' => now()->subDays(40)]);
    $disconnected = PostPlatform::factory()->facebook()->published()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->facebook()->tokenExpired(),
    ]);

    $this->actingAs($this->user)
        ->get(route('app.posts.show', $post))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('post.platforms', function ($platforms) use ($read, $unread, $lookedUp, $story, $tooOld, $disconnected): bool {
                $byId = collect($platforms)->keyBy('id');

                return data_get($byId, "{$read->id}.insights.views") === 321
                    && data_get($byId, "{$read->id}.insights.reel_plays") === 200
                    && array_key_exists('insights', $byId[$unread->id]) && $byId[$unread->id]['insights'] === null
                    && array_key_exists('insights', $byId[$lookedUp->id]) && $byId[$lookedUp->id]['insights'] === null
                    && ! array_key_exists('insights', $byId[$story->id])
                    && ! array_key_exists('insights', $byId[$tooOld->id])
                    && ! array_key_exists('insights', $byId[$disconnected->id]);
            })
        );
});

// Cleanup
test('disconnecting a Facebook Page deletes its Page insights and its Post insights', function () {
    $account = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    PageInsightSnapshot::factory()->count(2)->sequence(['date' => '2026-10-01'], ['date' => '2026-10-02'])->create(['social_account_id' => $account->id]);
    $post = Post::factory()->published()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    $postPlatform = PostPlatform::factory()->facebook()->published()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);
    PostInsight::factory()->create(['post_platform_id' => $postPlatform->id]);
    $otherPage = PostPlatform::factory()->facebook()->published()->create();
    PostInsight::factory()->create(['post_platform_id' => $otherPage->id]);

    $this->actingAs($this->user)
        ->delete(route('app.accounts.disconnect', $account))
        ->assertRedirect();

    expect(PageInsightSnapshot::query()->count())->toBe(0)
        ->and(PostInsight::query()->pluck('post_platform_id')->all())->toBe([$otherPage->id])
        ->and($postPlatform->fresh())->not->toBeNull();
});
