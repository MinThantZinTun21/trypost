<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config()->set('trypost.mobile.token', 'mobile-test-token');

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->headers = ['Authorization' => 'Bearer mobile-test-token'];

    $this->makePost = function (array $attributes = []): Post {
        $post = Post::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->owner->id,
            'content' => 'Hello',
            'media' => [[
                'id' => 'media-1',
                'path' => 'media/clip.mp4',
                'url' => 'https://cdn.example.com/media/clip.mp4',
                'type' => 'video',
                'mime_type' => 'video/mp4',
                'original_filename' => 'clip.mp4',
                'size' => 1024,
            ]],
            ...$attributes,
        ]);

        PostPlatform::factory()->facebookReel()->create([
            'post_id' => $post->id,
            'social_account_id' => SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id])->id,
        ]);

        return $post;
    };
});

test('post now publishes a scheduled post through the composer\'s path', function () {
    $this->freezeSecond();
    $post = ($this->makePost)(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDay()]);

    $this->postJson(route('mobile.v1.posts.publish', $post), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('id', $post->id)
        ->assertJsonPath('status', 'publishing')
        ->assertJsonPath('scheduled_at', now()->utc()->toIso8601String())
        ->assertJsonCount(1, 'platforms');

    expect($post->refresh()->status)->toBe(PostStatus::Publishing);
    Queue::assertPushed(PublishPost::class, fn (PublishPost $job): bool => $job->post->is($post));
});

test('a post that fails the publish checks stays scheduled, with the account\'s message', function () {
    $scheduledAt = now()->addDay()->startOfMinute();
    $post = ($this->makePost)(['status' => PostStatus::Scheduled, 'scheduled_at' => $scheduledAt]);
    $tiktok = PostPlatform::factory()->tiktok()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id])->id,
        'meta' => [],
    ]);

    $response = $this->postJson(route('mobile.v1.posts.publish', $post), [], $this->headers)->assertUnprocessable();

    $index = collect($this->getJson(route('mobile.v1.posts.show', $post), $this->headers)->json('platforms'))
        ->search(fn (array $platform): bool => $platform['id'] === $tiktok->id);
    $response->assertJsonValidationErrors(["platforms.{$index}.meta.privacy_level" => __('posts.form.tiktok.privacy_required')]);

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->scheduled_at->equalTo($scheduledAt))->toBeTrue();
    Queue::assertNotPushed(PublishPost::class);
});

test('a post that is publishing or finished cannot be posted now', function (PostStatus $status) {
    $post = ($this->makePost)(['status' => $status, 'scheduled_at' => now()->subMinute()]);

    $this->postJson(route('mobile.v1.posts.publish', $post), [], $this->headers)
        ->assertConflict()
        ->assertJsonPath('message', __('posts.cannot_edit_finalized'));

    expect($post->refresh()->status)->toBe($status);
    Queue::assertNotPushed(PublishPost::class);
})->with([
    'publishing' => [PostStatus::Publishing],
    'published' => [PostStatus::Published],
    'partially published' => [PostStatus::PartiallyPublished],
    'failed' => [PostStatus::Failed],
]);

test('another workspace\'s post is not found', function () {
    $post = Post::factory()->scheduled()->create();

    $this->postJson(route('mobile.v1.posts.publish', $post), [], $this->headers)->assertNotFound();

    Queue::assertNotPushed(PublishPost::class);
});

test('post now needs the token', function () {
    $post = ($this->makePost)(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDay()]);

    $this->postJson(route('mobile.v1.posts.publish', $post))->assertUnauthorized();
});
