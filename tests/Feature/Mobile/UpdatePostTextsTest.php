<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
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
    $this->video = [
        'id' => 'media-1',
        'path' => 'media/clip.mp4',
        'url' => 'https://cdn.example.com/media/clip.mp4',
        'type' => 'video',
        'mime_type' => 'video/mp4',
        'original_filename' => 'clip.mp4',
        'size' => 1024,
    ];

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now()->addDay(),
        'content' => 'Old content',
        'media' => [$this->video],
    ]);
    $this->facebook = PostPlatform::factory()->facebookReel()->create([
        'post_id' => $this->post->id,
        'social_account_id' => SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id])->id,
        'meta' => ['title' => 'Old reel title'],
    ]);
    $this->tiktok = PostPlatform::factory()->tiktok()->create([
        'post_id' => $this->post->id,
        'social_account_id' => SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id])->id,
        'meta' => ['privacy_level' => 'SELF_ONLY', 'caption' => 'Old caption'],
    ]);
    $this->youtube = PostPlatform::factory()->youtube()->create([
        'post_id' => $this->post->id,
        'social_account_id' => SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id])->id,
        'meta' => ['title' => 'Old title', 'description' => 'Old description', 'tags' => ['old']],
    ]);
    $this->disabled = PostPlatform::factory()->youtube()->disabled()->create(['post_id' => $this->post->id]);
});

test('each platform lists the texts its content type publishes', function () {
    $this->getJson(route('mobile.v1.posts.show', $this->post), $this->headers)
        ->assertOk()
        ->assertJsonPath('platforms.0.text_fields', ['title', 'description'])
        ->assertJsonPath('platforms.1.text_fields', ['caption'])
        ->assertJsonPath('platforms.2.text_fields', ['title', 'description', 'tags']);
});

test('the content and each account\'s texts save', function () {
    $this->putJson(route('mobile.v1.posts.update', $this->post), [
        'content' => 'New content',
        'platforms' => [
            ['id' => $this->facebook->id, 'meta' => ['title' => 'New reel title', 'description' => 'Reel description']],
            ['id' => $this->tiktok->id, 'meta' => ['caption' => 'New caption']],
            ['id' => $this->youtube->id, 'meta' => ['title' => 'New title', 'description' => null, 'tags' => ['one', 'two']]],
        ],
    ], $this->headers)
        ->assertOk()
        ->assertJsonPath('content', 'New content')
        ->assertJsonPath('status', 'scheduled')
        ->assertJsonPath('platforms.1.meta.caption', 'New caption')
        ->assertJsonPath('platforms.2.meta.tags', ['one', 'two']);

    $this->post->refresh();
    expect($this->post->content)->toBe('New content')
        ->and($this->post->status)->toBe(PostStatus::Scheduled)
        ->and($this->post->media)->toEqual([$this->video])
        ->and($this->facebook->refresh()->meta)->toEqual(['title' => 'New reel title', 'description' => 'Reel description'])
        ->and($this->tiktok->refresh()->meta)->toEqual(['privacy_level' => 'SELF_ONLY', 'caption' => 'New caption'])
        ->and($this->youtube->refresh()->meta)->toEqual(['title' => 'New title', 'tags' => ['one', 'two']]);
});

test('accounts, content types and media stay as they were', function () {
    $this->putJson(route('mobile.v1.posts.update', $this->post), [
        'platforms' => [['id' => $this->tiktok->id, 'meta' => ['caption' => 'Only this']]],
    ], $this->headers)->assertOk();

    expect($this->post->refresh()->content)->toBe('Old content')
        ->and($this->facebook->refresh()->enabled)->toBeTrue()
        ->and($this->facebook->content_type)->toBe(ContentType::FacebookReel)
        ->and($this->facebook->meta)->toEqual(['title' => 'Old reel title'])
        ->and($this->youtube->refresh()->enabled)->toBeTrue()
        ->and($this->youtube->meta)->toEqual(['title' => 'Old title', 'description' => 'Old description', 'tags' => ['old']])
        ->and($this->disabled->refresh()->enabled)->toBeFalse();
});

test('a text the content type does not publish is refused', function () {
    $this->putJson(route('mobile.v1.posts.update', $this->post), [
        'platforms' => [['id' => $this->tiktok->id, 'meta' => ['title' => 'A video has no title']]],
    ], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['platforms.0.meta.title' => 'does not publish a title']);

    expect($this->tiktok->refresh()->meta)->not->toHaveKey('title');
});

test('a text that is too long names its field', function () {
    $this->putJson(route('mobile.v1.posts.update', $this->post), [
        'platforms' => [
            ['id' => $this->facebook->id, 'meta' => []],
            ['id' => $this->tiktok->id, 'meta' => []],
            ['id' => $this->youtube->id, 'meta' => ['title' => str_repeat('a', 101)]],
        ],
    ], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('platforms.2.meta.title');

    expect($this->youtube->refresh()->meta['title'])->toBe('Old title');
});

test('only enabled accounts of this post can be edited', function () {
    $this->putJson(route('mobile.v1.posts.update', $this->post), [
        'platforms' => [['id' => $this->disabled->id, 'meta' => ['title' => 'Nope']]],
    ], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('platforms.0.id');
});

test('a scheduled post keeps the publish checks', function () {
    $this->tiktok->update(['meta' => []]);

    $this->putJson(route('mobile.v1.posts.update', $this->post), ['content' => 'New text'], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('platforms.1.meta.privacy_level');

    expect($this->post->refresh()->content)->toBe('Old content');
});

test('a post that is publishing or finished cannot be edited', function (PostStatus $status) {
    $this->post->update(['status' => $status]);

    $this->putJson(route('mobile.v1.posts.update', $this->post), ['content' => 'Too late'], $this->headers)
        ->assertConflict()
        ->assertJsonPath('message', __('posts.cannot_edit_finalized'));

    expect($this->post->refresh()->content)->toBe('Old content');
})->with([
    'publishing' => [PostStatus::Publishing],
    'published' => [PostStatus::Published],
    'partially published' => [PostStatus::PartiallyPublished],
    'failed' => [PostStatus::Failed],
]);

test('another workspace\'s post is not found', function () {
    $post = Post::factory()->scheduled()->create();

    $this->putJson(route('mobile.v1.posts.update', $post), ['content' => 'x'], $this->headers)
        ->assertNotFound();
});
