<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config()->set('trypost.mobile.token', 'mobile-test-token');

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->headers = ['Authorization' => 'Bearer mobile-test-token'];
    $this->video = [
        'id' => 'media-1',
        'path' => 'media/clip.mp4',
        'url' => 'https://cdn.example.com/media/clip.mp4',
        'type' => 'video',
        'mime_type' => 'video/mp4',
        'original_filename' => 'clip.mp4',
        'size' => 1024,
    ];

    $this->makePost = function (array $attributes = []): Post {
        $post = Post::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->owner->id,
            'content' => 'Hello',
            'media' => [$this->video],
            ...$attributes,
        ]);

        PostPlatform::factory()->facebookReel()->create([
            'post_id' => $post->id,
            'social_account_id' => SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id])->id,
        ]);
        PostPlatform::factory()->youtube()->create([
            'post_id' => $post->id,
            'social_account_id' => SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id])->id,
            'meta' => ['title' => 'Short', 'description' => 'About it'],
        ]);
        PostPlatform::factory()->tiktok()->disabled()->create(['post_id' => $post->id]);

        return $post;
    };

    $this->platformsOf = fn (Post $post): array => $post->postPlatforms()
        ->orderBy('id')
        ->get(['id', 'enabled', 'content_type', 'meta'])
        ->map(fn (PostPlatform $platform): array => [
            'id' => $platform->id,
            'enabled' => $platform->enabled,
            'content_type' => $platform->content_type,
            'meta' => $platform->meta,
        ])
        ->all();
});

test('rescheduling moves a scheduled post and keeps its accounts, content types and media', function () {
    $post = ($this->makePost)(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDay()]);
    $platformsBefore = ($this->platformsOf)($post);

    $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => '2030-03-04T05:06:00+00:00'], $this->headers)
        ->assertOk()
        ->assertJsonPath('id', $post->id)
        ->assertJsonPath('status', 'scheduled')
        ->assertJsonPath('scheduled_at', '2030-03-04T05:06:00+00:00')
        ->assertJsonCount(2, 'platforms');

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->scheduled_at->equalTo(Carbon::parse('2030-03-04T05:06:00Z')))->toBeTrue()
        ->and($post->media)->toEqual([$this->video])
        ->and($post->content)->toBe('Hello')
        ->and(($this->platformsOf)($post))->toEqual($platformsBefore);
});

test('a time in another timezone is stored in utc', function () {
    $post = ($this->makePost)(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDay()]);

    $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => '2030-03-04T12:36:00+06:30'], $this->headers)
        ->assertOk()
        ->assertJsonPath('scheduled_at', '2030-03-04T06:06:00+00:00');
});

test('a time without its utc offset is refused', function () {
    $post = ($this->makePost)(['status' => PostStatus::Draft]);

    foreach (['2030-03-04T12:36:00', '04-03-2030'] as $withoutOffset) {
        $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => $withoutOffset], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_at' => __('mobile.scheduled_at_offset')]);
    }

    $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => '2030-03-04T12:36:00Z'], $this->headers)
        ->assertOk()
        ->assertJsonPath('scheduled_at', '2030-03-04T12:36:00+00:00');

    $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => '2030-03-04T12:36:00.000+0630'], $this->headers)
        ->assertOk()
        ->assertJsonPath('scheduled_at', '2030-03-04T06:06:00+00:00');
});

test('rescheduling a draft schedules it', function () {
    $post = ($this->makePost)(['status' => PostStatus::Draft]);
    $platformsBefore = ($this->platformsOf)($post);

    $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => '2030-03-04T05:06:00+00:00'], $this->headers)
        ->assertOk()
        ->assertJsonPath('status', 'scheduled');

    expect($post->refresh()->status)->toBe(PostStatus::Scheduled)
        ->and(($this->platformsOf)($post))->toEqual($platformsBefore);
});

test('a time in the past is refused', function () {
    $post = ($this->makePost)(['status' => PostStatus::Draft]);

    $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => now()->subMinute()->toIso8601String()], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_at');

    $this->putJson(route('mobile.v1.posts.reschedule', $post), [], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_at');

    expect($post->refresh()->status)->toBe(PostStatus::Draft);
});

test('a draft that fails the publish checks stays a draft with the account\'s message', function () {
    $post = ($this->makePost)(['status' => PostStatus::Draft]);
    $tiktok = PostPlatform::factory()->tiktok()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id])->id,
        'meta' => [],
    ]);

    $response = $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => '2030-03-04T05:06:00+00:00'], $this->headers)
        ->assertUnprocessable();

    $index = collect($this->getJson(route('mobile.v1.posts.show', $post), $this->headers)->json('platforms'))
        ->search(fn (array $platform): bool => $platform['id'] === $tiktok->id);
    $response->assertJsonValidationErrors(["platforms.{$index}.meta.privacy_level" => __('posts.form.tiktok.privacy_required')]);

    expect($post->refresh()->status)->toBe(PostStatus::Draft)
        ->and($post->scheduled_at)->toBeNull();
});

test('a tiktok account with its privacy level schedules', function () {
    $post = ($this->makePost)(['status' => PostStatus::Draft]);
    PostPlatform::factory()->tiktok()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id])->id,
    ]);

    $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => '2030-03-04T05:06:00+00:00'], $this->headers)
        ->assertOk()
        ->assertJsonPath('status', 'scheduled');
});

test('a content type that does not fit the media is refused', function () {
    $post = ($this->makePost)(['status' => PostStatus::Draft, 'media' => []]);

    $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => '2030-03-04T05:06:00+00:00'], $this->headers)
        ->assertUnprocessable();

    expect($post->refresh()->status)->toBe(PostStatus::Draft);
});

test('unscheduling turns a scheduled post into a draft', function () {
    $post = ($this->makePost)(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDay()]);
    $platformsBefore = ($this->platformsOf)($post);

    $this->deleteJson(route('mobile.v1.posts.unschedule', $post), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('status', 'draft')
        ->assertJsonPath('editable', true);

    expect($post->refresh()->status)->toBe(PostStatus::Draft)
        ->and($post->media)->toEqual([$this->video])
        ->and(($this->platformsOf)($post))->toEqual($platformsBefore);
});

test('unscheduling a draft leaves it a draft', function () {
    $post = ($this->makePost)(['status' => PostStatus::Draft]);

    $this->deleteJson(route('mobile.v1.posts.unschedule', $post), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('status', 'draft');
});

test('a post that is publishing or finished cannot be rescheduled or unscheduled', function (PostStatus $status) {
    $post = ($this->makePost)(['status' => $status, 'scheduled_at' => now()->subMinute()]);

    $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => '2030-03-04T05:06:00+00:00'], $this->headers)
        ->assertConflict()
        ->assertJsonPath('message', __('posts.cannot_edit_finalized'));

    $this->deleteJson(route('mobile.v1.posts.unschedule', $post), [], $this->headers)
        ->assertConflict();

    expect($post->refresh()->status)->toBe($status);
})->with([
    'publishing' => [PostStatus::Publishing],
    'published' => [PostStatus::Published],
    'partially published' => [PostStatus::PartiallyPublished],
    'failed' => [PostStatus::Failed],
]);

test('another workspace\'s post is not found', function () {
    $post = Post::factory()->scheduled()->create();

    $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => '2030-03-04T05:06:00+00:00'], $this->headers)
        ->assertNotFound();
    $this->deleteJson(route('mobile.v1.posts.unschedule', $post), [], $this->headers)
        ->assertNotFound();
});

test('scheduling needs the token', function () {
    $post = ($this->makePost)(['status' => PostStatus::Draft]);

    $this->putJson(route('mobile.v1.posts.reschedule', $post), ['scheduled_at' => '2030-03-04T05:06:00+00:00'])
        ->assertUnauthorized();
    $this->deleteJson(route('mobile.v1.posts.unschedule', $post))
        ->assertUnauthorized();
});
