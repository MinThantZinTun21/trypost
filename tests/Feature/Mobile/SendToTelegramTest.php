<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\SendToTelegram;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config([
        'trypost.telegram.bot_token' => 'test-bot-token',
        'trypost.telegram.channel_id' => '@trypost_channel',
    ]);

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->headers = mobileHeaders($this->owner);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function telegramSendPost(array $attributes = [], bool $withVideo = true): Post
{
    $media = $withVideo
        ? [MediaItem::fromMedia(Media::factory()->video()->for(test()->workspace, 'mediable')->create())->toArray()]
        : [];

    return Post::factory()->create([
        'workspace_id' => test()->workspace->id,
        'user_id' => test()->owner->id,
        'media' => $media,
        ...$attributes,
    ]);
}

test('sending a post to telegram queues it and answers at once', function (PostStatus $status) {
    $post = telegramSendPost(['status' => $status]);

    $this->postJson(route('mobile.v1.posts.send', $post), [], $this->headers)
        ->assertAccepted()
        ->assertJsonPath('message', __('telegram.sending'));

    Queue::assertPushed(SendToTelegram::class, fn (SendToTelegram $job): bool => $job->post->is($post));
    expect($post->fresh()->status)->toBe($status);
})->with([
    'scheduled' => PostStatus::Scheduled,
    'published' => PostStatus::Published,
    'failed' => PostStatus::Failed,
]);

test('each press queues its own send', function () {
    $post = telegramSendPost();

    $this->postJson(route('mobile.v1.posts.send', $post), [], $this->headers)->assertAccepted();
    $this->postJson(route('mobile.v1.posts.send', $post), [], $this->headers)->assertAccepted();

    $sendIds = Queue::pushed(SendToTelegram::class)->map(fn (SendToTelegram $job): string => $job->sendId);
    expect($sendIds)->toHaveCount(2)
        ->and($sendIds->unique())->toHaveCount(2);
});

test('a post is not sent when telegram is not set up', function () {
    config(['trypost.telegram.bot_token' => '']);
    $post = telegramSendPost();

    $this->postJson(route('mobile.v1.posts.send', $post), [], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['telegram' => __('telegram.send_not_configured')]);

    Queue::assertNothingPushed();
});

test('a post without a video or photo is not sent', function () {
    $post = telegramSendPost(withVideo: false);

    $this->postJson(route('mobile.v1.posts.send', $post), [], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media' => __('telegram.no_media')]);

    Queue::assertNothingPushed();
});

test('another workspace\'s post is not sent', function () {
    $post = Post::factory()->create();

    $this->postJson(route('mobile.v1.posts.send', $post), [], $this->headers)->assertNotFound();

    Queue::assertNothingPushed();
});

test('sending needs the token', function () {
    $post = telegramSendPost();

    $this->postJson(route('mobile.v1.posts.send', $post))->assertUnauthorized();

    Queue::assertNothingPushed();
});
