<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config()->set('trypost.mobile.token', 'mobile-test-token');

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->headers = ['Authorization' => 'Bearer mobile-test-token'];
});

/**
 * @param  array<string, mixed>  $attributes
 */
function mobilePost(array $attributes = []): Post
{
    return Post::factory()->create([
        'workspace_id' => test()->workspace->id,
        'user_id' => test()->owner->id,
        ...$attributes,
    ]);
}

/**
 * @return list<string>
 */
function mobilePostIds(string $status): array
{
    return collect(test()->getJson(route('mobile.v1.posts.index', ['status' => $status]), test()->headers)
        ->assertOk()
        ->json('data'))
        ->pluck('id')
        ->all();
}

test('the scheduled tab lists scheduled and publishing posts, soonest first', function () {
    $later = mobilePost(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDays(3)]);
    $sooner = mobilePost(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addHour()]);
    $publishing = mobilePost(['status' => PostStatus::Publishing, 'scheduled_at' => now()->subMinute()]);
    mobilePost(['status' => PostStatus::Failed, 'scheduled_at' => now()->subHour()]);
    mobilePost(['status' => PostStatus::Published, 'published_at' => now()]);

    expect(mobilePostIds('scheduled'))->toBe([$publishing->id, $sooner->id, $later->id]);
});

test('the published tab lists fully published posts, newest first', function () {
    $older = mobilePost(['status' => PostStatus::Published, 'published_at' => now()->subDays(2)]);
    $newer = mobilePost(['status' => PostStatus::Published, 'published_at' => now()->subDay()]);
    mobilePost(['status' => PostStatus::PartiallyPublished, 'published_at' => now()]);

    expect(mobilePostIds('published'))->toBe([$newer->id, $older->id]);
});

test('the failed tab lists failed and partially published posts, newest first', function () {
    $failed = mobilePost(['status' => PostStatus::Failed, 'scheduled_at' => now()->subDays(2)]);
    $partial = mobilePost(['status' => PostStatus::PartiallyPublished, 'scheduled_at' => now()->subDay(), 'published_at' => now()->subDay()]);
    mobilePost(['status' => PostStatus::Published, 'published_at' => now()]);

    expect(mobilePostIds('failed'))->toBe([$partial->id, $failed->id]);
});

test('another workspace\'s posts never appear', function () {
    $mine = mobilePost(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDay()]);
    Post::factory()->scheduled()->create();

    expect(mobilePostIds('scheduled'))->toBe([$mine->id]);
});

test('the list is paginated with the app page size', function () {
    config()->set('app.pagination.default', 2);
    foreach (range(1, 3) as $day) {
        mobilePost(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDays($day)]);
    }

    $this->getJson(route('mobile.v1.posts.index', ['status' => 'scheduled']), $this->headers)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson(route('mobile.v1.posts.index', ['status' => 'scheduled', 'page' => 2]), $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('the status must be one of the tabs', function (?string $status) {
    $this->getJson(route('mobile.v1.posts.index', array_filter(['status' => $status])), $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
})->with([
    'missing' => [null],
    'partially published' => ['partially_published'],
    'unknown' => ['all'],
]);

test('each post carries its enabled post platforms, social accounts and media, with UTC ISO 8601 times', function () {
    $media = Media::factory()->for($this->workspace, 'mediable')->create(['original_filename' => 'cover.jpg']);
    $post = mobilePost([
        'status' => PostStatus::Scheduled,
        'content' => 'Hello from the phone',
        'scheduled_at' => '2030-01-02 09:30:00',
        'media' => [MediaItem::fromMedia($media)->toArray()],
    ]);
    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'display_name' => 'My Page',
        'username' => 'mypage',
    ]);
    $enabled = PostPlatform::factory()->facebook()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'meta' => ['aspect_ratio' => 'original'],
    ]);
    PostPlatform::factory()->youtube()->disabled()->create(['post_id' => $post->id]);

    $this->getJson(route('mobile.v1.posts.index', ['status' => 'scheduled']), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.0.id', $post->id)
        ->assertJsonPath('data.0.status', 'scheduled')
        ->assertJsonPath('data.0.content', 'Hello from the phone')
        ->assertJsonPath('data.0.scheduled_at', '2030-01-02T09:30:00+00:00')
        ->assertJsonPath('data.0.published_at', null)
        ->assertJsonPath('data.0.editable', true)
        ->assertJsonPath('data.0.media.0.id', $media->id)
        ->assertJsonPath('data.0.media.0.type', 'image')
        ->assertJsonPath('data.0.media.0.url', $media->url)
        ->assertJsonPath('data.0.media.0.original_filename', 'cover.jpg')
        ->assertJsonCount(1, 'data.0.platforms')
        ->assertJsonPath('data.0.platforms.0.id', $enabled->id)
        ->assertJsonPath('data.0.platforms.0.platform', 'facebook')
        ->assertJsonPath('data.0.platforms.0.content_type', 'facebook_post')
        ->assertJsonPath('data.0.platforms.0.status', PlatformStatus::Pending->value)
        ->assertJsonPath('data.0.platforms.0.platform_url', null)
        ->assertJsonPath('data.0.platforms.0.error_message', null)
        ->assertJsonPath('data.0.platforms.0.social_account.id', $account->id)
        ->assertJsonPath('data.0.platforms.0.social_account.display_name', 'My Page')
        ->assertJsonPath('data.0.platforms.0.social_account.username', 'mypage');
});

test('a finalized post is not editable', function () {
    mobilePost(['status' => PostStatus::Published, 'published_at' => now()]);

    $this->getJson(route('mobile.v1.posts.index', ['status' => 'published']), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.0.editable', false);
});
