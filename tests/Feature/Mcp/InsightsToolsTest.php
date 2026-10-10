<?php

declare(strict_types=1);

use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\GetPageInsights;
use App\Mcp\Tools\GetPostInsights;
use App\Models\PageInsightSnapshot;
use App\Models\Post;
use App\Models\PostInsight;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    $this->travelTo(now()->parse('2026-10-09 10:00:00'));
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

test('get_page_insights reports the stored totals for the first Facebook Page', function () {
    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'insights_read_at' => now()->subHours(7),
    ]);
    PageInsightSnapshot::factory()->create(['social_account_id' => $account->id, 'date' => '2026-10-05', 'views' => 80, 'followers' => 410]);
    PageInsightSnapshot::factory()->create(['social_account_id' => $account->id, 'date' => '2026-09-30', 'views' => 20, 'followers' => 400]);
    PageInsightSnapshot::factory()->create(['social_account_id' => $account->id, 'date' => '2026-10-08', 'views' => 0, 'followers' => 410]);

    SchedulerServer::actingAs($this->owner)
        ->tool(GetPageInsights::class, ['days' => 7])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('social_account_id', $account->id)
            ->where('read_at', now()->subHours(7)->toIso8601String())
            ->where('last_error', null)
            ->where('days', 7)
            ->where('from', '2026-10-02')
            ->where('to', '2026-10-08')
            ->where('metrics.0', ['key' => 'followers', 'current' => 410, 'previous' => 400])
            ->where('metrics.3', ['key' => 'views', 'current' => 80, 'previous' => 20])
            ->where('top_posts', [])
            ->etc()
        );
});

test('get_page_insights refuses a missing or non-Facebook Social account', function () {
    SchedulerServer::actingAs($this->owner)
        ->tool(GetPageInsights::class, [])
        ->assertHasErrors([__('mcp.insights.no_facebook_page')]);

    $tiktok = SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id]);
    $elsewhere = SocialAccount::factory()->facebook()->create();

    foreach ([$tiktok, $elsewhere] as $account) {
        SchedulerServer::actingAs($this->owner)
            ->tool(GetPageInsights::class, ['social_account_id' => $account->id])
            ->assertHasErrors([__('mcp.insights.account_not_found')]);
    }
});

test('get_page_insights rejects a range it does not offer', function () {
    SchedulerServer::actingAs($this->owner)
        ->tool(GetPageInsights::class, ['days' => 30])
        ->assertHasErrors();
});

test('get_post_insights returns stored Post insights for each Facebook Page', function () {
    $post = Post::factory()->published()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $reel = PostPlatform::factory()->facebookReel()->published()->create(['post_id' => $post->id]);
    PostInsight::factory()->create(['post_platform_id' => $reel->id, 'views' => 77, 'reel_plays' => 50]);
    $unread = PostPlatform::factory()->facebook()->published()->create(['post_id' => $post->id]);
    PostPlatform::factory()->tiktok()->published()->create(['post_id' => $post->id]);

    SchedulerServer::actingAs($this->owner)
        ->tool(GetPostInsights::class, ['post_id' => $post->id])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($post, $reel, $unread) {
            $json->where('post_id', $post->id)
                ->has('social_accounts', 2)
                ->where('social_accounts', fn ($accounts) => collect($accounts)->firstWhere('social_account_id', $reel->social_account_id)['insights']['views'] === 77
                    && collect($accounts)->firstWhere('social_account_id', $reel->social_account_id)['insights']['reel_plays'] === 50
                    && collect($accounts)->firstWhere('social_account_id', $unread->social_account_id)['insights'] === null);
        });
});

test('get_post_insights refuses another workspace\'s Post', function () {
    $post = Post::factory()->published()->create();

    SchedulerServer::actingAs($this->owner)
        ->tool(GetPostInsights::class, ['post_id' => $post->id])
        ->assertHasErrors([__('mcp.post.not_found')]);
});
