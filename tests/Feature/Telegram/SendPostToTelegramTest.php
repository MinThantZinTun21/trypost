<?php

declare(strict_types=1);

use App\Actions\Post\FinalizePostPublication;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\SendPostToTelegram;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'trypost.telegram.bot_token' => 'test-bot-token',
        'trypost.telegram.channel_id' => '@trypost_mirror',
    ]);

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
});

/**
 * A scheduled Post with one enabled target per given factory state.
 *
 * @param  list<array{0: string, 1: string}>  $targets  [platform state, status state]
 */
function telegramMirrorPost(Workspace $workspace, User $owner, array $targets, array $attributes = []): Post
{
    $post = Post::factory()->scheduled()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'content' => 'Our new video is live!',
        ...$attributes,
    ]);

    foreach ($targets as [$platform, $status]) {
        $account = SocialAccount::factory()->{$platform}()->create([
            'workspace_id' => $workspace->id,
            'username' => "{$platform}-account",
        ]);

        PostPlatform::factory()->{$platform}()->{$status}()->create([
            'post_id' => $post->id,
            'social_account_id' => $account->id,
            'enabled' => true,
        ]);
    }

    return $post;
}

/**
 * @return list<Request>
 */
function telegramRequests(string $method): array
{
    $api = config('trypost.telegram.api');

    return Http::recorded(fn (Request $request): bool => $request->url() === "{$api}/bottest-bot-token/{$method}")
        ->map(fn (array $pair): Request => $pair[0])
        ->values()
        ->all();
}

function fakeTelegram(): void
{
    $api = config('trypost.telegram.api');

    Http::fake(["{$api}/*" => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
}

test('a published post is queued for the Telegram mirror once', function () {
    Queue::fake();
    $post = telegramMirrorPost($this->workspace, $this->owner, [['facebook', 'published']]);

    app(FinalizePostPublication::class)->handle($post);
    app(FinalizePostPublication::class)->handle($post);

    Queue::assertPushed(SendPostToTelegram::class, 1);
    Queue::assertPushed(SendPostToTelegram::class, fn (SendPostToTelegram $job): bool => $job->post->is($post));
});

test('a partly published post is queued for the Telegram mirror', function () {
    Queue::fake();
    $post = telegramMirrorPost($this->workspace, $this->owner, [['facebook', 'failed'], ['tiktok', 'published']]);

    app(FinalizePostPublication::class)->handle($post);

    Queue::assertPushed(SendPostToTelegram::class, 1);
});

test('a failed post is not mirrored', function () {
    Queue::fake();
    $post = telegramMirrorPost($this->workspace, $this->owner, [['facebook', 'failed']]);

    app(FinalizePostPublication::class)->handle($post);

    Queue::assertNotPushed(SendPostToTelegram::class);
});

test('nothing is queued while the mirror is not configured', function (string $key) {
    Queue::fake();
    config(["trypost.telegram.{$key}" => '']);
    $post = telegramMirrorPost($this->workspace, $this->owner, [['facebook', 'published']]);

    app(FinalizePostPublication::class)->handle($post);

    Queue::assertNotPushed(SendPostToTelegram::class);
})->with(['bot_token', 'channel_id']);

test('the mirror sends the content and a live link per published platform', function () {
    fakeTelegram();
    $post = telegramMirrorPost($this->workspace, $this->owner, [
        ['facebook', 'published'],
        ['tiktok', 'published'],
        ['youtube', 'failed'],
    ]);
    $post->update(['status' => PostStatus::PartiallyPublished]);
    $facebook = $post->postPlatforms()->where('platform', 'facebook')->first();
    $tiktok = $post->postPlatforms()->where('platform', 'tiktok')->first();

    SendPostToTelegram::dispatchSync($post);

    $requests = telegramRequests('sendMessage');
    expect($requests)->toHaveCount(1);

    $text = data_get($requests[0]->data(), 'text');
    expect(data_get($requests[0]->data(), 'chat_id'))->toBe('@trypost_mirror')
        ->and($text)->toStartWith('Our new video is live!')
        ->and($text)->toContain(__('telegram.live_on'))
        ->and($text)->toContain("{$facebook->notificationLabel()}: {$facebook->platform_url}")
        ->and($text)->toContain("{$tiktok->notificationLabel()}: {$tiktok->platform_url}")
        ->and($text)->not->toContain('YouTube');
    expect($post->fresh()->telegram_sent_at)->not->toBeNull();
});

test('an empty post falls back to its YouTube title', function () {
    fakeTelegram();
    $post = telegramMirrorPost($this->workspace, $this->owner, [['youtube', 'published']], ['content' => '']);
    $post->update(['status' => PostStatus::Published]);
    $post->postPlatforms()->first()->update(['meta' => ['title' => 'Behind the scenes']]);

    SendPostToTelegram::dispatchSync($post);

    expect(data_get(telegramRequests('sendMessage')[0]->data(), 'text'))->toStartWith('Behind the scenes');
});

test('text over the Telegram limit is split across messages', function () {
    fakeTelegram();
    $content = Str::repeat('a', 5000);
    $post = telegramMirrorPost($this->workspace, $this->owner, [['facebook', 'published']], ['content' => $content]);
    $post->update(['status' => PostStatus::Published]);

    SendPostToTelegram::dispatchSync($post);

    $texts = collect(telegramRequests('sendMessage'))->map(fn (Request $request): string => data_get($request->data(), 'text'));
    expect($texts)->toHaveCount(2)
        ->and($texts->every(fn (string $text): bool => mb_strlen($text) <= 4096))->toBeTrue()
        ->and($texts->implode(''))->toContain($content);
});

test('a post already mirrored is not sent again', function () {
    fakeTelegram();
    $post = telegramMirrorPost($this->workspace, $this->owner, [['facebook', 'published']]);
    $post->update(['status' => PostStatus::Published, 'telegram_sent_at' => now()]);

    SendPostToTelegram::dispatchSync($post);

    Http::assertNothingSent();
});

test('a post that is not published is not sent', function () {
    fakeTelegram();
    $post = telegramMirrorPost($this->workspace, $this->owner, [['facebook', 'failed']]);
    $post->update(['status' => PostStatus::Failed]);

    SendPostToTelegram::dispatchSync($post);

    Http::assertNothingSent();
    expect($post->fresh()->telegram_sent_at)->toBeNull();
});

test('the job sends nothing once the mirror is turned off', function () {
    fakeTelegram();
    $post = telegramMirrorPost($this->workspace, $this->owner, [['facebook', 'published']]);
    $post->update(['status' => PostStatus::Published]);
    config(['trypost.telegram.bot_token' => '']);

    SendPostToTelegram::dispatchSync($post);

    Http::assertNothingSent();
});
