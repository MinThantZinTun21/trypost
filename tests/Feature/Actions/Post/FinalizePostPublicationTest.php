<?php

declare(strict_types=1);

use App\Actions\Post\FinalizePostPublication;
use App\Enums\Notification\Type;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\SendNotification;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('a fully published post sends no notification', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $workspace->id,
    ]);
    $post = Post::factory()->scheduled()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);
    PostPlatform::factory()->facebook()->published()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'enabled' => true,
    ]);

    app(FinalizePostPublication::class)->handle($post);

    expect($post->fresh()->status)->toBe(PostStatus::Published);
    Queue::assertNotPushed(SendNotification::class);
});

test('a partly published post notifies the owner about the failed platforms', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $facebook = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $workspace->id,
        'username' => 'inbox',
    ]);
    $tiktok = SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $workspace->id,
    ]);
    $post = Post::factory()->scheduled()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);
    PostPlatform::factory()->facebook()->failed()->create([
        'post_id' => $post->id,
        'social_account_id' => $facebook->id,
        'enabled' => true,
    ]);
    PostPlatform::factory()->tiktok()->published()->create([
        'post_id' => $post->id,
        'social_account_id' => $tiktok->id,
        'enabled' => true,
    ]);

    app(FinalizePostPublication::class)->handle($post);

    expect($post->fresh()->status)->toBe(PostStatus::PartiallyPublished);
    Queue::assertPushed(SendNotification::class, function (SendNotification $job) use ($post) {
        return $job->type === Type::PostFailed
            && $job->body === __('notifications.post_failed.body', ['platforms' => 'Facebook Page (@inbox)'])
            && data_get($job->data, 'post_id') === $post->id;
    });
});

test('failed notification is sent to the owner', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $workspace->id,
        'username' => 'inbox',
        'display_name' => 'InboxPlacement.io',
    ]);
    $post = Post::factory()->scheduled()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);
    $postPlatform = PostPlatform::factory()->facebook()->failed()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'enabled' => true,
    ]);

    app(FinalizePostPublication::class)->handle($post);

    Queue::assertPushed(SendNotification::class, function (SendNotification $job) use ($post) {
        $platforms = 'Facebook Page (@inbox)';

        return $job->type === Type::PostFailed
            && $job->title === __('notifications.post_failed.title')
            && $job->body === __('notifications.post_failed.body', ['platforms' => $platforms])
            && data_get($job->data, 'post_id') === $post->id;
    });
});

test('a publishing post with no enabled targets is failed', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'status' => PostStatus::Publishing,
    ]);
    PostPlatform::factory()->facebook()->disabled()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->facebook()->create([
            'workspace_id' => $workspace->id,
        ])->id,
    ]);

    app(FinalizePostPublication::class)->handle($post);

    expect($post->fresh()->status)->toBe(PostStatus::Failed);
    Queue::assertPushed(SendNotification::class, fn (SendNotification $job) => $job->type === Type::PostFailed);
});

test('a draft with no enabled targets is left alone', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'status' => PostStatus::Draft,
    ]);
    PostPlatform::factory()->facebook()->disabled()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->facebook()->create([
            'workspace_id' => $workspace->id,
        ])->id,
    ]);

    app(FinalizePostPublication::class)->handle($post);

    expect($post->fresh()->status)->toBe(PostStatus::Draft);
    Queue::assertNotPushed(SendNotification::class);
});

test('a second settle does not notify again', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'status' => PostStatus::Publishing,
    ]);
    PostPlatform::factory()->facebook()->failed()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->facebook()->create([
            'workspace_id' => $workspace->id,
        ])->id,
        'enabled' => true,
    ]);

    $finalize = app(FinalizePostPublication::class);
    $finalize->handle($post);
    $finalize->handle($post->fresh());

    expect($post->fresh()->status)->toBe(PostStatus::Failed);
    Queue::assertPushedTimes(SendNotification::class, 1);
});

test('an already settled post is left alone', function (PostStatus $status) {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'status' => $status,
        'published_at' => $status === PostStatus::Failed ? null : now(),
    ]);
    PostPlatform::factory()->facebook()->published()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->facebook()->create([
            'workspace_id' => $workspace->id,
        ])->id,
        'enabled' => true,
    ]);

    app(FinalizePostPublication::class)->handle($post);

    expect($post->fresh()->status)->toBe($status);
    Queue::assertNotPushed(SendNotification::class);
})->with([
    PostStatus::Published,
    PostStatus::PartiallyPublished,
    PostStatus::Failed,
]);
