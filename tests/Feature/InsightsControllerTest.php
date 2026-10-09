<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\PageInsightSnapshot;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

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
