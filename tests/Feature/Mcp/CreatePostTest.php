<?php

declare(strict_types=1);

use App\Enums\Post\CreatedVia;
use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Jobs\PublishPost;
use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\CreatePost;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->facebook = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    $this->tiktok = SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id]);
    $this->youtube = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
});

function ownerAsset(string $state = 'image'): Media
{
    $factory = Media::factory()->assets()->for(test()->workspace, 'mediable');

    return ($state === 'video' ? $factory->video() : $factory)->create(['meta' => $state === 'video' ? ['duration' => 30] : null]);
}

/**
 * @param  array<string, mixed>  $arguments
 */
function createPostWith(array $arguments): mixed
{
    return SchedulerServer::actingAs(test()->owner)->tool(CreatePost::class, $arguments);
}

function onlyPost(): Post
{
    return Post::query()->sole();
}

test('a video draft defaults to Reel, TikTok Video and YouTube Short and is created via mcp', function () {
    $video = ownerAsset('video');

    createPostWith([
        'content' => 'Launch day',
        'media_ids' => [$video->id],
        'mode' => 'draft',
        'social_accounts' => [
            ['id' => $this->facebook->id],
            ['id' => $this->tiktok->id, 'privacy_level' => 'SELF_ONLY'],
            ['id' => $this->youtube->id],
        ],
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('post.id', onlyPost()->id)
        ->where('post.status', 'draft')
        ->where('post.scheduled_at', null)
        ->where('post.goes_out', __('mcp.post.goes_out_never')));

    $post = onlyPost();
    $enabled = $post->postPlatforms()->enabled()->get()->keyBy('social_account_id');

    expect($post->created_via)->toBe(CreatedVia::Mcp)
        ->and($post->status)->toBe(Status::Draft)
        ->and($post->content)->toBe('Launch day')
        ->and(array_column($post->media, 'id'))->toBe([$video->id])
        ->and($enabled)->toHaveCount(3)
        ->and($enabled[$this->facebook->id]->content_type)->toBe(ContentType::FacebookReel)
        ->and($enabled[$this->tiktok->id]->content_type)->toBe(ContentType::TikTokVideo)
        ->and($enabled[$this->youtube->id]->content_type)->toBe(ContentType::YouTubeShort);
    Queue::assertNothingPushed();
});

test('an image post defaults to Facebook Post and TikTok Photo', function () {
    $image = ownerAsset();

    createPostWith([
        'media_ids' => [$image->id],
        'mode' => 'draft',
        'social_accounts' => [
            ['id' => $this->facebook->id],
            ['id' => $this->tiktok->id, 'privacy_level' => 'SELF_ONLY'],
        ],
    ])->assertOk();

    $enabled = onlyPost()->postPlatforms()->enabled()->get()->keyBy('social_account_id');

    expect($enabled[$this->facebook->id]->content_type)->toBe(ContentType::FacebookPost)
        ->and($enabled[$this->tiktok->id]->content_type)->toBe(ContentType::TikTokPhoto);
});

test('a given content type wins over the default', function () {
    createPostWith([
        'media_ids' => [ownerAsset('video')->id],
        'mode' => 'draft',
        'social_accounts' => [['id' => $this->facebook->id, 'content_type' => 'facebook_story']],
    ])->assertOk();

    expect(onlyPost()->postPlatforms()->enabled()->sole()->content_type)->toBe(ContentType::FacebookStory);
});

test('titles, descriptions, captions and TikTok settings are stored on each account', function () {
    createPostWith([
        'content' => 'Fallback text',
        'media_ids' => [ownerAsset('video')->id],
        'mode' => 'draft',
        'social_accounts' => [
            ['id' => $this->facebook->id, 'title' => 'Reel title', 'description' => 'Reel description', 'privacy_level' => 'SELF_ONLY'],
            ['id' => $this->tiktok->id, 'caption' => 'TikTok caption', 'privacy_level' => 'SELF_ONLY', 'allow_comments' => true, 'allow_duet' => false, 'allow_stitch' => false, 'is_aigc' => true],
            ['id' => $this->youtube->id, 'title' => 'Short title', 'description' => 'Short description'],
        ],
    ])->assertOk();

    $enabled = onlyPost()->postPlatforms()->enabled()->get()->keyBy('social_account_id');

    expect($enabled[$this->facebook->id]->meta)->toEqual(['title' => 'Reel title', 'description' => 'Reel description'])
        ->and($enabled[$this->tiktok->id]->meta)->toEqual([
            'caption' => 'TikTok caption',
            'privacy_level' => 'SELF_ONLY',
            'allow_comments' => true,
            'allow_duet' => false,
            'allow_stitch' => false,
            'is_aigc' => true,
        ])
        ->and($enabled[$this->youtube->id]->meta)->toEqual(['title' => 'Short title', 'description' => 'Short description']);
});

test('a scheduled post is stored in UTC and says when it goes out', function () {
    $this->freezeSecond();

    createPostWith([
        'content' => 'Tomorrow',
        'mode' => 'scheduled',
        'scheduled_at' => now()->addDay()->setTimezone('Asia/Bangkok')->toIso8601String(),
        'social_accounts' => [['id' => $this->facebook->id]],
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('post.status', 'scheduled')
        ->where('post.scheduled_at', now()->addDay()->utc()->toIso8601String())
        ->where('post.goes_out', __('mcp.post.goes_out_at', ['time' => now()->addDay()->utc()->toIso8601String()]))
        ->etc());

    expect(onlyPost()->status)->toBe(Status::Scheduled)
        ->and(onlyPost()->scheduled_at->equalTo(now()->addDay()))->toBeTrue();
    Queue::assertNothingPushed();
});

test('publishing now queues the post like the composer', function () {
    createPostWith([
        'content' => 'Right now',
        'mode' => 'now',
        'social_accounts' => [['id' => $this->facebook->id]],
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('post.status', 'publishing')
        ->where('post.goes_out', __('mcp.post.goes_out_now'))
        ->etc());

    expect(onlyPost()->status)->toBe(Status::Publishing);
    Queue::assertPushed(PublishPost::class);
});

test('create_post rejects input before saving anything', function (Closure $arguments, string $message) {
    createPostWith($arguments())->assertHasErrors([$message]);

    expect(Post::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();
})->with([
    'schedule time without an offset' => [
        fn () => ['mode' => 'scheduled', 'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i:s'), 'social_accounts' => [['id' => test()->facebook->id]]],
        fn () => __('mcp.post.scheduled_at_offset'),
    ],
    'schedule time in the past' => [
        fn () => ['mode' => 'scheduled', 'scheduled_at' => now()->subHour()->toIso8601String(), 'social_accounts' => [['id' => test()->facebook->id]]],
        fn () => __('validation.after', ['attribute' => 'scheduled at', 'date' => 'now']),
    ],
    'scheduled without a time' => [
        fn () => ['mode' => 'scheduled', 'social_accounts' => [['id' => test()->facebook->id]]],
        fn () => __('validation.required_if', ['attribute' => 'scheduled at', 'other' => 'mode', 'value' => 'scheduled']),
    ],
    'TikTok draft without privacy' => [
        fn () => ['mode' => 'draft', 'social_accounts' => [['id' => test()->tiktok->id]]],
        fn () => __('mcp.post.tiktok_privacy_required', ['account' => test()->tiktok->accountDisplayName()]),
    ],
    'unknown account' => [
        fn () => ['mode' => 'draft', 'social_accounts' => [['id' => SocialAccount::factory()->create()->id]]],
        fn () => __('mcp.post.account_not_found'),
    ],
    'unknown media' => [
        fn () => ['mode' => 'draft', 'media_ids' => [Media::factory()->assets()->for(Workspace::factory(), 'mediable')->create()->id], 'social_accounts' => [['id' => test()->facebook->id]]],
        fn () => __('mcp.post.media_not_found'),
    ],
    'content type from another platform' => [
        fn () => ['mode' => 'draft', 'social_accounts' => [['id' => test()->facebook->id, 'content_type' => 'youtube_short']]],
        fn () => __('mcp.post.content_type_platform', ['type' => 'youtube_short', 'account' => test()->facebook->accountDisplayName()]),
    ],
]);

test('create_post returns the composer\'s errors and rolls the post back', function (Closure $arguments, string $message) {
    createPostWith($arguments())->assertHasErrors([$message]);

    expect(Post::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();
})->with([
    'media the content type does not accept' => [
        fn () => ['mode' => 'now', 'media_ids' => [ownerAsset()->id], 'social_accounts' => [['id' => test()->youtube->id]]],
        fn () => __('posts.form.warnings.no_image_allowed'),
    ],
    'YouTube title over its limit' => [
        fn () => ['mode' => 'draft', 'social_accounts' => [['id' => test()->youtube->id, 'title' => str_repeat('a', 101)]]],
        fn () => __('posts.form.youtube.title_max'),
    ],
    'TikTok photo title over its limit' => [
        fn () => ['mode' => 'draft', 'media_ids' => [ownerAsset()->id], 'social_accounts' => [['id' => test()->tiktok->id, 'privacy_level' => 'SELF_ONLY', 'title' => str_repeat('a', 91)]]],
        fn () => __('posts.form.tiktok.photo_title_max'),
    ],
    'YouTube description over its limit' => [
        fn () => ['mode' => 'scheduled', 'scheduled_at' => now()->addDay()->toIso8601String(), 'media_ids' => [ownerAsset('video')->id], 'social_accounts' => [['id' => test()->youtube->id, 'description' => str_repeat('a', 5001)]]],
        fn () => __('posts.form.youtube.description_max'),
    ],
]);
