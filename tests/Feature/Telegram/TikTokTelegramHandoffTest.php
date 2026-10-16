<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Enums\SocialAccount\Status as AccountStatus;
use App\Jobs\PublishToSocialPlatform;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\FacebookPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Storage::fake();
    config([
        'trypost.platforms.tiktok.delivery' => 'telegram',
        'trypost.telegram.bot_token' => 'test-bot-token',
        'trypost.telegram.channel_id' => '@trypost_channel',
    ]);

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->tiktokAccount = SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id]);

    $telegram = config('trypost.telegram.api');
    $tiktok = config('trypost.platforms.tiktok.api');
    $this->telegramReply = fn () => Http::response(['ok' => true, 'result' => []]);
    Http::fake([
        "{$telegram}/*" => fn (Request $request) => ($this->telegramReply)($request),
        "{$tiktok}/*" => Http::response([], Response::HTTP_INTERNAL_SERVER_ERROR),
    ]);
});

/**
 * A publishing Post with one TikTok target carrying the given media.
 *
 * @param  list<array{type: string, size?: int}>  $items
 * @param  array<string, mixed>  $meta
 */
function tiktokHandoffTarget(array $items, array $meta = [], ?string $content = 'From the post', ContentType $contentType = ContentType::TikTokVideo): PostPlatform
{
    $test = test();

    $media = collect($items)->map(function (array $item, int $index) use ($test): array {
        $isVideo = $item['type'] === 'video';
        $extension = $isVideo ? 'mp4' : 'jpg';
        $path = "medias/item-{$index}.{$extension}";
        Storage::put($path, "bytes-{$index}");

        $model = ($isVideo ? Media::factory()->video() : Media::factory())->for($test->workspace, 'mediable')->create([
            'path' => $path,
            'original_filename' => "item-{$index}.{$extension}",
            'size' => $item['size'] ?? 1024,
        ]);

        return MediaItem::fromMedia($model)->toArray();
    })->all();

    $post = Post::factory()->create([
        'workspace_id' => $test->workspace->id,
        'user_id' => $test->owner->id,
        'content' => $content,
        'media' => $media,
        'status' => PostStatus::Publishing,
    ]);

    return PostPlatform::factory()->tiktok()->create([
        'post_id' => $post->id,
        'social_account_id' => $test->tiktokAccount->id,
        'content_type' => $contentType,
        'meta' => $meta,
        'enabled' => true,
    ]);
}

/**
 * @return list<Request>
 */
function handoffRequests(?string $method = null): array
{
    $api = config('trypost.telegram.api');

    return Http::recorded(fn (Request $request): bool => str_starts_with($request->url(), "{$api}/bottest-bot-token/")
        && ($method === null || $request->url() === "{$api}/bottest-bot-token/{$method}"))
        ->map(fn (array $pair): Request => $pair[0])
        ->values()
        ->all();
}

function handoffField(Request $request, string $name): mixed
{
    if (! $request->isMultipart()) {
        return data_get($request->data(), $name);
    }

    return data_get(collect($request->data())->firstWhere('name', $name), 'contents');
}

function runHandoff(PostPlatform $target): void
{
    (new PublishToSocialPlatform($target))->handle();
}

test('a TikTok video goes to Telegram with its download link, then its caption as a separate message', function () {
    $target = tiktokHandoffTarget([['type' => 'video']], ['caption' => 'Watch till the end #fyp']);
    $mediaId = data_get($target->post->media, '0.id');

    runHandoff($target);

    $requests = handoffRequests();
    expect($requests)->toHaveCount(2);

    $video = $requests[0];
    expect($video->url())->toEndWith('/sendVideo')
        ->and($video->hasFile('video', null, 'item-0.mp4'))->toBeTrue()
        ->and(handoffField($video, 'chat_id'))->toBe('@trypost_channel')
        ->and(handoffField($video, 'caption'))->toStartWith('Download item-0.mp4: ')
        ->and(handoffField($video, 'caption'))->toContain(route('media.download', ['media' => $mediaId]));

    $caption = $requests[1];
    expect($caption->url())->toEndWith('/sendMessage')
        ->and(handoffField($caption, 'text'))->toBe('Watch till the end #fyp');

    Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), config('trypost.platforms.tiktok.api')));
    expect($target->fresh())
        ->status->toBe(PlatformStatus::Published)
        ->platform_url->toBeNull()
        ->and($target->post->fresh()->status)->toBe(PostStatus::Published);
});

test('without a TikTok caption the post content is sent as plain text', function () {
    $target = tiktokHandoffTarget([['type' => 'video']], content: '<p>New <strong>drop</strong> today</p>');

    runHandoff($target);

    expect(handoffField(handoffRequests('sendMessage')[0], 'text'))->toBe('New drop today');
});

test('a video over the upload limit is sent as its download link only', function () {
    $target = tiktokHandoffTarget([['type' => 'video', 'size' => 60 * 1024 * 1024]], ['caption' => 'Long one']);

    runHandoff($target);

    expect(handoffRequests('sendVideo'))->toBeEmpty();
    $messages = handoffRequests('sendMessage');
    expect($messages)->toHaveCount(2)
        ->and(handoffField($messages[0], 'text'))->toStartWith('Download item-0.mp4: ')
        ->and(handoffField($messages[1], 'text'))->toBe('Long one');
});

test('a video without a caption or content sends only the video', function () {
    $target = tiktokHandoffTarget([['type' => 'video']], content: null);

    runHandoff($target);

    expect(handoffRequests())->toHaveCount(1)
        ->and(handoffRequests('sendVideo'))->toHaveCount(1);
});

test('TikTok photos go as an album, then the title and description as a separate message', function () {
    $target = tiktokHandoffTarget(
        [['type' => 'image'], ['type' => 'image'], ['type' => 'image']],
        ['title' => 'Behind the scenes', 'description' => 'Swipe for more'],
        contentType: ContentType::TikTokPhoto,
    );

    runHandoff($target);

    $requests = handoffRequests();
    expect($requests)->toHaveCount(2)
        ->and($requests[0]->url())->toEndWith('/sendMediaGroup')
        ->and(collect(json_decode(handoffField($requests[0], 'media'), true))->pluck('caption')->filter())->toBeEmpty()
        ->and(handoffField($requests[1], 'text'))->toBe("Behind the scenes\n\nSwipe for more");
});

test('more than ten photos are split into albums of ten', function () {
    $target = tiktokHandoffTarget(array_fill(0, 12, ['type' => 'image']), contentType: ContentType::TikTokPhoto);

    runHandoff($target);

    $albums = handoffRequests('sendMediaGroup');
    expect($albums)->toHaveCount(2)
        ->and(json_decode(handoffField($albums[0], 'media'), true))->toHaveCount(10)
        ->and(json_decode(handoffField($albums[1], 'media'), true))->toHaveCount(2);
});

test('a TikTok post goes to Telegram even when the TikTok account is expired or disconnected', function (AccountStatus $status) {
    $this->tiktokAccount->update(['status' => $status]);
    $target = tiktokHandoffTarget([['type' => 'video']], ['caption' => 'Still goes out']);

    runHandoff($target);

    expect($target->fresh()->status)->toBe(PlatformStatus::Published)
        ->and(handoffRequests())->toHaveCount(2);
})->with([
    'token expired' => [AccountStatus::TokenExpired],
    'disconnected' => [AccountStatus::Disconnected],
]);

test('the TikTok target fails when Telegram is not set up', function () {
    config(['trypost.telegram.bot_token' => null]);
    $target = tiktokHandoffTarget([['type' => 'video']]);

    runHandoff($target);

    expect($target->fresh())
        ->status->toBe(PlatformStatus::Failed)
        ->error_message->toBe(__('telegram.not_configured'))
        ->and($target->post->fresh()->status)->toBe(PostStatus::Failed);
    Http::assertNothingSent();
});

test('a refusal fails the TikTok target with the reason Telegram gave', function () {
    $this->telegramReply = fn () => Http::response(['ok' => false, 'description' => 'Forbidden: bot is not a member of the channel chat'], Response::HTTP_FORBIDDEN);
    $target = tiktokHandoffTarget([['type' => 'video']]);

    runHandoff($target);

    expect($target->fresh())
        ->status->toBe(PlatformStatus::Failed)
        ->error_message->toBe(__('telegram.refused', ['reason' => 'Forbidden: bot is not a member of the channel chat']));
});

test('a long 429 retries the TikTok target as late as Telegram asks', function () {
    Sleep::fake();
    Queue::fake();
    $this->telegramReply = fn () => Http::response([
        'ok' => false,
        'description' => 'Too Many Requests: retry after 120',
        'parameters' => ['retry_after' => 120],
    ], Response::HTTP_TOO_MANY_REQUESTS);
    $target = tiktokHandoffTarget([['type' => 'video']]);

    runHandoff($target);

    expect($target->fresh())
        ->status->toBe(PlatformStatus::Retrying)
        ->and(data_get($target->fresh()->error_context, 'retry_delay_seconds'))->toBe(120);
    Queue::assertPushed(PublishToSocialPlatform::class, fn (PublishToSocialPlatform $job): bool => $job->postPlatform->is($target));
    Sleep::assertNeverSlept();
});

test('a retry resumes after the messages that already went out', function () {
    Queue::fake();
    $this->telegramReply = Http::sequence()
        ->push(['ok' => true, 'result' => []])
        ->push(['ok' => false, 'description' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR)
        ->push(['ok' => true, 'result' => []]);
    $target = tiktokHandoffTarget([['type' => 'video']], ['caption' => 'Second half']);

    runHandoff($target);
    expect($target->fresh()->status)->toBe(PlatformStatus::Retrying);

    runHandoff($target->fresh());

    expect(handoffRequests('sendVideo'))->toHaveCount(1)
        ->and(handoffRequests('sendMessage'))->toHaveCount(2)
        ->and($target->fresh()->status)->toBe(PlatformStatus::Published);
});

test('with API delivery a TikTok post is not sent to Telegram', function () {
    config(['trypost.platforms.tiktok.delivery' => 'api']);
    $target = tiktokHandoffTarget([['type' => 'video']], ['caption' => 'Through the API']);

    runHandoff($target);

    expect(handoffRequests())->toBeEmpty();
});

test('a published Facebook post is no longer copied to Telegram', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'content' => 'Facebook only',
        'status' => PostStatus::Publishing,
    ]);
    $target = PostPlatform::factory()->facebook()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id])->id,
        'enabled' => true,
    ]);
    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andReturn(['id' => 'fb-1', 'url' => 'https://facebook.com/fb-1']);
    $this->app->instance(FacebookPublisher::class, $publisher);

    runHandoff($target);

    expect($post->fresh()->status)->toBe(PostStatus::Published)
        ->and(handoffRequests())->toBeEmpty();
});
