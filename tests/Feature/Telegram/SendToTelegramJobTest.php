<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\Notification\Type as NotificationType;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Jobs\SendToTelegram;
use App\Models\Media;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Storage::fake();
    Sleep::fake();
    config([
        'trypost.telegram.bot_token' => 'test-bot-token',
        'trypost.telegram.channel_id' => '@trypost_channel',
    ]);

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);

    $this->telegramReply = fn () => Http::response(['ok' => true, 'result' => []]);
    Http::fake([
        config('trypost.telegram.api').'/*' => fn (Request $request) => ($this->telegramReply)($request),
    ]);
});

/**
 * A Post carrying the given media, stored in the fake disk.
 *
 * @param  list<string>  $types  'video' or 'image'
 * @param  array<string, mixed>  $attributes
 */
function telegramJobPost(array $types, array $attributes = []): Post
{
    $test = test();

    $media = collect($types)->map(function (string $type, int $index) use ($test): array {
        $isVideo = $type === 'video';
        $extension = $isVideo ? 'mp4' : 'jpg';
        $path = "medias/send-{$index}.{$extension}";
        Storage::put($path, "bytes-{$index}");

        $model = ($isVideo ? Media::factory()->video() : Media::factory())->for($test->workspace, 'mediable')->create([
            'path' => $path,
            'original_filename' => "send-{$index}.{$extension}",
            'size' => 1024,
        ]);

        return MediaItem::fromMedia($model)->toArray();
    })->all();

    return Post::factory()->published()->create([
        'workspace_id' => $test->workspace->id,
        'user_id' => $test->owner->id,
        'content' => '<p>From the <strong>post</strong></p>',
        'media' => $media,
        ...$attributes,
    ]);
}

/**
 * @return list<Request>
 */
function telegramJobRequests(): array
{
    $api = config('trypost.telegram.api');

    return Http::recorded(fn (Request $request): bool => str_starts_with($request->url(), "{$api}/bottest-bot-token/"))
        ->map(fn (array $pair): Request => $pair[0])
        ->values()
        ->all();
}

function telegramJobField(Request $request, string $name): mixed
{
    if (! $request->isMultipart()) {
        return data_get($request->data(), $name);
    }

    return data_get(collect($request->data())->firstWhere('name', $name), 'contents');
}

function runTelegramSend(Post $post, ?string $sendId = null): SendToTelegram
{
    $job = new SendToTelegram($post, $sendId ?? (string) Str::uuid());
    $job->withFakeQueueInteractions();
    $job->handle();

    return $job;
}

test('without a TikTok target the videos, then the photos, then the post content go out', function () {
    $post = telegramJobPost(['image', 'video', 'image']);

    runTelegramSend($post);

    $requests = telegramJobRequests();
    expect(array_map(fn (Request $request): string => Str::afterLast($request->url(), '/'), $requests))
        ->toBe(['sendVideo', 'sendMediaGroup', 'sendMessage'])
        ->and(telegramJobField($requests[0], 'caption'))->toStartWith('Download send-1.mp4: ')
        ->and(telegramJobField($requests[2], 'text'))->toBe('From the post');
});

test('with a TikTok target its own handoff goes out', function () {
    $post = telegramJobPost(['video']);
    PostPlatform::factory()->tiktok()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id])->id,
        'content_type' => ContentType::TikTokVideo,
        'meta' => ['caption' => 'Watch till the end #fyp'],
        'enabled' => true,
        'status' => PlatformStatus::Published,
    ]);

    runTelegramSend($post);

    $requests = telegramJobRequests();
    expect($requests)->toHaveCount(2)
        ->and($requests[0]->url())->toEndWith('/sendVideo')
        ->and(telegramJobField($requests[1], 'text'))->toBe('Watch till the end #fyp');
});

test('the post and its platforms are left as they were', function () {
    $post = telegramJobPost(['video']);
    $platform = PostPlatform::factory()->create(['post_id' => $post->id, 'enabled' => true, 'status' => PlatformStatus::Published]);
    $before = $platform->fresh()->getAttributes();

    runTelegramSend($post);

    expect($post->fresh()->status)->toBe(PostStatus::Published)
        ->and($platform->fresh()->getAttributes())->toEqual($before);
});

test('a retry after a rate limit does not send the video again', function () {
    $post = telegramJobPost(['video']);
    $sendId = (string) Str::uuid();
    $this->telegramReply = fn (Request $request) => str_ends_with($request->url(), '/sendMessage')
        ? Http::response(['ok' => false, 'description' => 'Too Many Requests: retry after 60', 'parameters' => ['retry_after' => 60]], Response::HTTP_TOO_MANY_REQUESTS)
        : Http::response(['ok' => true, 'result' => []]);

    $job = runTelegramSend($post, $sendId);

    $job->assertReleased(60);
    expect(Notification::query()->count())->toBe(0);

    $this->telegramReply = fn () => Http::response(['ok' => true, 'result' => []]);
    runTelegramSend($post, $sendId);

    $urls = array_map(fn (Request $request): string => Str::afterLast($request->url(), '/'), telegramJobRequests());
    expect(array_count_values($urls))->toBe(['sendVideo' => 1, 'sendMessage' => 2]);
});

test('a server error or lost connection is retried', function () {
    $post = telegramJobPost(['video']);
    $this->telegramReply = fn () => Http::response(['ok' => false, 'description' => 'Bad Gateway'], Response::HTTP_BAD_GATEWAY);

    runTelegramSend($post)->assertReleased();

    expect(Notification::query()->count())->toBe(0);
});

test('a refusal fails the send and tells the owner why', function () {
    $post = telegramJobPost(['video']);
    $this->telegramReply = fn () => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], Response::HTTP_BAD_REQUEST);

    SendToTelegram::dispatchSync($post, (string) Str::uuid());

    expect(telegramJobRequests())->toHaveCount(1);
    $notification = Notification::query()->sole();
    expect($notification->type)->toBe(NotificationType::TelegramSendFailed)
        ->and($notification->title)->toBe(__('notifications.telegram_send_failed.title'))
        ->and($notification->body)->toContain('chat not found')
        ->and($notification->data)->toEqual(['post_id' => $post->id]);
});

test('running out of retries tells the owner', function () {
    $post = telegramJobPost(['video']);
    $job = new SendToTelegram($post, (string) Str::uuid());

    $job->failed(new RuntimeException('Connection timed out'));

    expect(Notification::query()->sole())
        ->type->toBe(NotificationType::TelegramSendFailed)
        ->body->toContain('Connection timed out');
});
