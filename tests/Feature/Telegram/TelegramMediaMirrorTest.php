<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\SendPostToTelegram;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Telegram\TelegramClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake();
    config([
        'trypost.telegram.bot_token' => 'test-bot-token',
        'trypost.telegram.channel_id' => '@trypost_mirror',
    ]);

    $api = config('trypost.telegram.api');
    Http::fake(["{$api}/*" => Http::response(['ok' => true, 'result' => []])]);
});

/**
 * A published Post on one Facebook Page carrying the given media items.
 *
 * @param  list<array{type: string, size?: int}>  $items
 */
function telegramMediaPost(array $items, string $content = 'Fresh from the shoot'): Post
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $owner->id]);

    $media = collect($items)->map(function (array $item, int $index) use ($workspace): array {
        $isVideo = $item['type'] === 'video';
        $extension = $isVideo ? 'mp4' : 'jpg';
        $path = "medias/item-{$index}.{$extension}";
        Storage::put($path, "bytes-{$index}");

        $model = ($isVideo ? Media::factory()->video() : Media::factory())->for($workspace, 'mediable')->create([
            'path' => $path,
            'original_filename' => "item-{$index}.{$extension}",
            'size' => $item['size'] ?? 1024,
        ]);

        return MediaItem::fromMedia($model)->toArray();
    })->all();

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'content' => $content,
        'media' => $media,
        'status' => PostStatus::Published,
    ]);
    PostPlatform::factory()->facebook()->published()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->facebook()->create(['workspace_id' => $workspace->id])->id,
    ]);

    return $post;
}

/**
 * @return list<Request>
 */
function telegramMediaRequests(string $method): array
{
    $api = config('trypost.telegram.api');

    return Http::recorded(fn (Request $request): bool => $request->url() === "{$api}/bottest-bot-token/{$method}")
        ->map(fn (array $pair): Request => $pair[0])
        ->values()
        ->all();
}

function multipartField(Request $request, string $name): mixed
{
    return data_get(collect($request->data())->firstWhere('name', $name), 'contents');
}

test('one image is uploaded with its text as the caption', function () {
    $post = telegramMediaPost([['type' => 'image']]);

    SendPostToTelegram::dispatchSync($post);

    $photos = telegramMediaRequests('sendPhoto');
    expect($photos)->toHaveCount(1)
        ->and($photos[0]->isMultipart())->toBeTrue()
        ->and($photos[0]->hasFile('photo', null, 'item-0.jpg'))->toBeTrue()
        ->and(multipartField($photos[0], 'chat_id'))->toBe('@trypost_mirror')
        ->and(multipartField($photos[0], 'caption'))->toStartWith('Fresh from the shoot');
    expect(telegramMediaRequests('sendMessage'))->toBeEmpty();
});

test('one small video is uploaded with its text as the caption', function () {
    $post = telegramMediaPost([['type' => 'video', 'size' => 40 * 1024 * 1024]]);

    SendPostToTelegram::dispatchSync($post);

    $videos = telegramMediaRequests('sendVideo');
    expect($videos)->toHaveCount(1)
        ->and($videos[0]->hasFile('video', null, 'item-0.mp4'))->toBeTrue()
        ->and(multipartField($videos[0], 'supports_streaming'))->toBe('true')
        ->and(multipartField($videos[0], 'caption'))->toStartWith('Fresh from the shoot');
});

test('several images go as an album with the caption on the first item', function () {
    $post = telegramMediaPost([['type' => 'image'], ['type' => 'image'], ['type' => 'image']]);

    SendPostToTelegram::dispatchSync($post);

    $albums = telegramMediaRequests('sendMediaGroup');
    expect($albums)->toHaveCount(1);

    $media = json_decode((string) multipartField($albums[0], 'media'), true);
    expect($media)->toHaveCount(3)
        ->and(collect($media)->pluck('type')->unique()->all())->toBe(['photo'])
        ->and(collect($media)->pluck('media')->all())->toBe(['attach://file0', 'attach://file1', 'attach://file2'])
        ->and($media[0]['caption'])->toStartWith('Fresh from the shoot')
        ->and(array_key_exists('caption', $media[1]))->toBeFalse()
        ->and($albums[0]->hasFile('file2', null, 'item-2.jpg'))->toBeTrue();
});

test('more than ten images are split into albums of ten', function () {
    $post = telegramMediaPost(array_fill(0, 12, ['type' => 'image']));

    SendPostToTelegram::dispatchSync($post);

    $albums = telegramMediaRequests('sendMediaGroup');
    expect($albums)->toHaveCount(2)
        ->and(json_decode((string) multipartField($albums[0], 'media'), true))->toHaveCount(10)
        ->and(json_decode((string) multipartField($albums[1], 'media'), true))->toHaveCount(2);
});

test('text over the caption limit follows the media as messages', function () {
    $content = Str::repeat('b', TelegramClient::CAPTION_LIMIT + 1);
    $post = telegramMediaPost([['type' => 'image']], $content);

    SendPostToTelegram::dispatchSync($post);

    $photo = telegramMediaRequests('sendPhoto')[0];
    expect(multipartField($photo, 'caption'))->toBeNull();

    $text = collect(telegramMediaRequests('sendMessage'))
        ->map(fn (Request $request): string => data_get($request->data(), 'text'))
        ->implode('');
    expect($text)->toContain($content);
});

test('an image over the upload limit is left out without failing the mirror', function () {
    $post = telegramMediaPost([
        ['type' => 'image', 'size' => TelegramClient::PHOTO_UPLOAD_LIMIT + 1],
        ['type' => 'image'],
    ]);

    SendPostToTelegram::dispatchSync($post);

    $photos = telegramMediaRequests('sendPhoto');
    expect($photos)->toHaveCount(1)
        ->and($photos[0]->hasFile('photo', null, 'item-1.jpg'))->toBeTrue();
    expect(telegramMediaRequests('sendMediaGroup'))->toBeEmpty();
    expect($post->fresh()->telegram_sent_at)->not->toBeNull();
});

test('a post whose only image is too big is sent as text', function () {
    $post = telegramMediaPost([['type' => 'image', 'size' => TelegramClient::PHOTO_UPLOAD_LIMIT + 1]]);

    SendPostToTelegram::dispatchSync($post);

    expect(telegramMediaRequests('sendPhoto'))->toBeEmpty()
        ->and(telegramMediaRequests('sendMessage'))->toHaveCount(1);
});

test('a video over the upload limit is sent as text with its download link', function () {
    $post = telegramMediaPost([['type' => 'video', 'size' => TelegramClient::FILE_UPLOAD_LIMIT + 1]]);
    $link = URL::signedRoute('media.download', ['media' => $post->media_items->first()->id]);

    SendPostToTelegram::dispatchSync($post);

    expect(telegramMediaRequests('sendVideo'))->toBeEmpty();
    expect(data_get(telegramMediaRequests('sendMessage')[0]->data(), 'text'))
        ->toContain(__('telegram.download', ['name' => 'item-0.mp4']))
        ->toContain($link);
});

test('an uploaded video also carries its download link', function () {
    $post = telegramMediaPost([['type' => 'video']]);
    $link = URL::signedRoute('media.download', ['media' => $post->media_items->first()->id]);

    SendPostToTelegram::dispatchSync($post);

    expect(multipartField(telegramMediaRequests('sendVideo')[0], 'caption'))->toContain($link);
});

test('images get no download link', function () {
    $post = telegramMediaPost([['type' => 'image']]);

    SendPostToTelegram::dispatchSync($post);

    expect(multipartField(telegramMediaRequests('sendPhoto')[0], 'caption'))->not->toContain('/download');
});

test('an album never carries more than the album byte limit in one request', function () {
    $thirtyMegabytes = 30 * 1024 * 1024;
    $post = telegramMediaPost([
        ['type' => 'video', 'size' => $thirtyMegabytes],
        ['type' => 'video', 'size' => $thirtyMegabytes],
        ['type' => 'image'],
    ]);

    SendPostToTelegram::dispatchSync($post);

    expect(telegramMediaRequests('sendVideo'))->toHaveCount(1);
    $albums = telegramMediaRequests('sendMediaGroup');
    expect($albums)->toHaveCount(1)
        ->and(json_decode((string) multipartField($albums[0], 'media'), true))->toHaveCount(2);
});

test('a file missing from storage is left out without failing the mirror', function () {
    $post = telegramMediaPost([['type' => 'image'], ['type' => 'image']]);
    Storage::delete('medias/item-0.jpg');

    SendPostToTelegram::dispatchSync($post);

    $photos = telegramMediaRequests('sendPhoto');
    expect($photos)->toHaveCount(1)
        ->and($photos[0]->hasFile('photo', null, 'item-1.jpg'))->toBeTrue()
        ->and($post->fresh()->telegram_sent_at)->not->toBeNull();
});

test('the caption limit counts emoji the way Telegram does', function () {
    $content = Str::repeat('😀', 600);
    $post = telegramMediaPost([['type' => 'image']], $content);

    SendPostToTelegram::dispatchSync($post);

    expect(multipartField(telegramMediaRequests('sendPhoto')[0], 'caption'))->toBeNull()
        ->and(data_get(telegramMediaRequests('sendMessage')[0]->data(), 'text'))->toContain($content);
});
