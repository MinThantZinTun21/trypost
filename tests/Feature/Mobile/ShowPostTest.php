<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\Status as PlatformStatus;
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

test('a post shows each social account with its outcome', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'status' => PostStatus::PartiallyPublished,
        'content' => 'Full text of the post',
    ]);
    $published = PostPlatform::factory()->youtube()->published()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id])->id,
        'platform_url' => 'https://youtube.com/shorts/abc',
        'meta' => ['title' => 'My short', 'description' => 'About it', 'tags' => ['one', 'two']],
    ]);
    $failed = PostPlatform::factory()->facebook()->failed()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id])->id,
        'error_message' => 'The Page token expired.',
    ]);
    PostPlatform::factory()->tiktok()->disabled()->create(['post_id' => $post->id]);

    $this->getJson(route('mobile.v1.posts.show', $post), $this->headers)
        ->assertOk()
        ->assertJsonPath('id', $post->id)
        ->assertJsonPath('status', 'partially_published')
        ->assertJsonPath('content', 'Full text of the post')
        ->assertJsonPath('editable', false)
        ->assertJsonCount(2, 'platforms')
        ->assertJsonPath('platforms.0.id', $published->id)
        ->assertJsonPath('platforms.0.status', PlatformStatus::Published->value)
        ->assertJsonPath('platforms.0.platform_url', 'https://youtube.com/shorts/abc')
        ->assertJsonPath('platforms.0.meta.title', 'My short')
        ->assertJsonPath('platforms.0.meta.tags', ['one', 'two'])
        ->assertJsonPath('platforms.1.id', $failed->id)
        ->assertJsonPath('platforms.1.status', PlatformStatus::Failed->value)
        ->assertJsonPath('platforms.1.error_message', 'The Page token expired.');
});

test('a post handed off to telegram is published without a link', function () {
    $post = Post::factory()->published()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    PostPlatform::factory()->tiktok()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id])->id,
        'status' => PlatformStatus::Published,
        'platform_url' => null,
    ]);

    $this->getJson(route('mobile.v1.posts.show', $post), $this->headers)
        ->assertOk()
        ->assertJsonPath('platforms.0.platform', 'tiktok')
        ->assertJsonPath('platforms.0.status', 'published')
        ->assertJsonPath('platforms.0.platform_url', null);
});

test('an empty meta is an object, not a list', function () {
    $post = Post::factory()->draft()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    PostPlatform::factory()->facebook()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id])->id,
        'meta' => [],
    ]);

    expect($this->getJson(route('mobile.v1.posts.show', $post), $this->headers)->assertOk()->getContent())
        ->toContain('"meta":{}');
});

test('another workspace\'s post is not found', function () {
    $post = Post::factory()->draft()->create();

    $this->getJson(route('mobile.v1.posts.show', $post), $this->headers)->assertNotFound();
});

test('a post needs the mobile token', function () {
    $post = Post::factory()->draft()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);

    $this->getJson(route('mobile.v1.posts.show', $post))->assertUnauthorized();
});
