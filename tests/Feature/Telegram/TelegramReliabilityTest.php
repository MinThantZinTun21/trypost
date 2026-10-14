<?php

declare(strict_types=1);

use App\Enums\Notification\Type;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\SendPostToTelegram;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Telegram\PostMirror;
use App\Services\Telegram\TelegramClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'trypost.telegram.bot_token' => 'test-bot-token',
        'trypost.telegram.channel_id' => '@trypost_mirror',
    ]);

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'content' => 'Our new video is live!',
        'status' => PostStatus::Published,
    ]);
    PostPlatform::factory()->facebook()->published()->create([
        'post_id' => $this->post->id,
        'social_account_id' => SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id])->id,
    ]);
});

/**
 * Telegram answers every Bot API call with this status and body.
 *
 * @param  array<string, mixed>  $body
 */
function telegramAnswers(int $status, array $body): void
{
    $api = config('trypost.telegram.api');

    Http::fake(["{$api}/*" => Http::response(['ok' => false, ...$body], $status)]);
}

test('a 429 waits as long as Telegram asks before the next try', function () {
    telegramAnswers(Response::HTTP_TOO_MANY_REQUESTS, [
        'description' => 'Too Many Requests: retry after 17',
        'parameters' => ['retry_after' => 17],
    ]);
    $job = (new SendPostToTelegram($this->post))->withFakeQueueInteractions();

    $job->handle(app(TelegramClient::class), app(PostMirror::class));

    $job->assertReleased(17);
    expect($this->post->fresh()->telegram_sent_at)->toBeNull();
});

test('a refusal a retry cannot fix fails the job at once', function () {
    telegramAnswers(Response::HTTP_FORBIDDEN, ['description' => 'Forbidden: bot is not a member of the channel chat']);
    $job = (new SendPostToTelegram($this->post))->withFakeQueueInteractions();

    $job->handle(app(TelegramClient::class), app(PostMirror::class));

    $job->assertFailedWith(RequestException::class);
});

test('a server error is thrown so the job retries with its backoff', function () {
    telegramAnswers(Response::HTTP_BAD_GATEWAY, ['description' => 'Bad Gateway']);
    $job = (new SendPostToTelegram($this->post))->withFakeQueueInteractions();

    expect(fn () => $job->handle(app(TelegramClient::class), app(PostMirror::class)))
        ->toThrow(RequestException::class);
    $job->assertNotFailed();
    $job->assertNotReleased();
});

test('the final failure tells the Owner once and leaves the post published', function () {
    telegramAnswers(Response::HTTP_FORBIDDEN, ['description' => 'Forbidden: bot is not a member of the channel chat']);

    SendPostToTelegram::dispatch($this->post);

    $notifications = Notification::query()->where('type', Type::TelegramFailed)->get();
    expect($notifications)->toHaveCount(1)
        ->and($notifications[0]->user_id)->toBe($this->owner->id)
        ->and($notifications[0]->title)->toBe(__('notifications.telegram_failed.title'))
        ->and($notifications[0]->body)->toContain('bot is not a member of the channel chat')
        ->and(data_get($notifications[0]->data, 'post_id'))->toBe($this->post->id);

    $post = $this->post->fresh();
    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->telegram_sent_at)->toBeNull();
});
