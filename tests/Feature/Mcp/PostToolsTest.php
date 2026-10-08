<?php

declare(strict_types=1);

use App\Enums\Post\Status;
use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\GetPost;
use App\Mcp\Tools\ListPosts;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

function ownerPost(array $attributes = [], string $state = 'draft'): Post
{
    return Post::factory()->{$state}()->create([
        'workspace_id' => test()->workspace->id,
        'user_id' => test()->owner->id,
        ...$attributes,
    ]);
}

test('list_posts filters by status', function (string $status, array $expected) {
    $draft = ownerPost(['content' => 'Draft post']);
    $scheduled = ownerPost(['content' => 'Scheduled post'], 'scheduled');
    $published = ownerPost(['content' => 'Published post'], 'published');
    $partial = ownerPost(['content' => 'Partial post', 'status' => Status::PartiallyPublished], 'published');
    $ids = ['draft' => $draft->id, 'scheduled' => $scheduled->id, 'published' => $published->id, 'partial' => $partial->id];

    SchedulerServer::actingAs($this->owner)
        ->tool(ListPosts::class, ['status' => $status])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($expected, $ids) {
            expect(array_column($json->toArray()['posts'], 'id'))
                ->toEqualCanonicalizing(array_map(fn (string $key): string => $ids[$key], $expected));
            $json->etc();
        });
})->with([
    'draft' => ['draft', ['draft']],
    'scheduled' => ['scheduled', ['scheduled']],
    'published includes partially published' => ['published', ['published', 'partial']],
]);

test('list_posts puts the newest scheduled time first and undated drafts last', function () {
    $undated = ownerPost(['scheduled_at' => null]);
    $earlier = ownerPost(['scheduled_at' => now()->addDay()]);
    $later = ownerPost(['scheduled_at' => now()->addDays(2)]);

    SchedulerServer::actingAs($this->owner)
        ->tool(ListPosts::class, ['status' => 'draft'])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($undated, $earlier, $later) {
            expect(array_column($json->toArray()['posts'], 'id'))->toBe([$later->id, $earlier->id, $undated->id]);
            $json->etc();
        });
});

test('list_posts pages with the app page size', function () {
    config(['app.pagination.default' => 2]);
    collect(range(1, 3))->each(fn (int $day) => ownerPost(['scheduled_at' => now()->addDays($day)], 'scheduled'));

    SchedulerServer::actingAs($this->owner)
        ->tool(ListPosts::class, ['page' => 2])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('posts', 1)
            ->where('page', 2)
            ->where('last_page', 2)
            ->where('total', 3));
});

test('list_posts shows each post\'s social accounts and leaves out other workspaces', function () {
    $post = ownerPost(['content' => '<p>Hello from the composer</p>'], 'scheduled');
    $account = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id, 'display_name' => 'Lucky News']);
    PostPlatform::factory()->facebook()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);
    Post::factory()->create(['workspace_id' => Workspace::factory()->create()->id]);

    SchedulerServer::actingAs($this->owner)
        ->tool(ListPosts::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('posts', 1)
            ->where('posts.0.id', $post->id)
            ->where('posts.0.content', 'Hello from the composer')
            ->where('posts.0.social_accounts.0.display_name', 'Lucky News')
            ->where('posts.0.social_accounts.0.platform', 'facebook')
            ->etc());
});

test('list_posts rejects an unknown status', function () {
    SchedulerServer::actingAs($this->owner)
        ->tool(ListPosts::class, ['status' => 'publishing'])
        ->assertHasErrors();
});

test('get_post returns each social account\'s status, error and link', function () {
    $post = ownerPost(['content' => 'Video for every platform'], 'published');
    $facebook = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    $tiktok = SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id]);
    $published = PostPlatform::factory()->facebookReel()->published()->create(['post_id' => $post->id, 'social_account_id' => $facebook->id, 'platform_url' => 'https://www.facebook.com/reel/1']);
    PostPlatform::factory()->tiktok()->failed()->create(['post_id' => $post->id, 'social_account_id' => $tiktok->id, 'error_message' => 'App not approved for public posting.']);

    SchedulerServer::actingAs($this->owner)
        ->tool(GetPost::class, ['id' => $post->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('post.id', $post->id)
            ->where('post.content', 'Video for every platform')
            ->has('post.social_accounts', 2)
            ->where('post.social_accounts', fn ($accounts) => collect($accounts)->contains(fn (array $account) => $account['social_account_id'] === $facebook->id
                && $account['status'] === 'published'
                && $account['content_type'] === 'facebook_reel'
                && $account['url'] === 'https://www.facebook.com/reel/1'
                && $account['published_at'] === $published->published_at->toIso8601String())
                && collect($accounts)->contains(fn (array $account) => $account['social_account_id'] === $tiktok->id
                    && $account['status'] === 'failed'
                    && $account['error_message'] === 'App not approved for public posting.'
                    && $account['url'] === null))
            ->etc());
});

test('get_post does not find a post outside the owner\'s workspace', function (Closure $id) {
    SchedulerServer::actingAs($this->owner)
        ->tool(GetPost::class, ['id' => $id()])
        ->assertHasErrors();
})->with([
    'other workspace' => [fn () => Post::factory()->create(['workspace_id' => Workspace::factory()->create()->id])->id],
    'unknown' => [fn () => fake()->uuid()],
    'not a uuid' => [fn () => '42'],
]);
