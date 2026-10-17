<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Notification\Type;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\Social\TikTokPublishException;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Services\Social\ContentSanitizer;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramSteps;
use App\Services\Telegram\TikTokHandoff;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Send to Telegram (ADR 0012): the Owner's manual copy of one Post to the
 * Telegram channel. With an enabled TikTok target it sends that target's
 * handoff; otherwise the videos, then the photos, then the Post content.
 * The Post and its platforms never change.
 *
 * Each press has its own $sendId, so a retry skips the steps already sent
 * while a second press sends again. A refusal or running out of retries
 * leaves a TelegramSendFailed Notification.
 */
class SendToTelegram implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    /** A 50 MB upload with headroom; keep the database queue's retry_after above this. */
    public int $timeout = 900;

    /** Seconds before retrying a server error or a lost connection. */
    private const int RETRY_DELAY_SECONDS = 60;

    /** Hours the last retried error is kept for the failure Notification. */
    private const int LAST_ERROR_HOURS = 24;

    public function __construct(
        public Post $post,
        public string $sendId,
    ) {}

    public function handle(): void
    {
        $telegram = app(TelegramClient::class);

        if (! $telegram->isConfigured()) {
            $this->fail(new RuntimeException(__('telegram.send_not_configured')));

            return;
        }

        try {
            $steps = $this->steps();
        } catch (TikTokPublishException $exception) {
            $this->fail(new RuntimeException($exception->userMessage));

            return;
        }

        if ($steps === []) {
            $this->fail(new RuntimeException(__('telegram.no_media')));

            return;
        }

        try {
            app(TelegramSteps::class)->deliver("telegram-send:{$this->sendId}:sent-steps", $steps);
        } catch (ConnectionException $exception) {
            $this->retryLater($exception->getMessage(), self::RETRY_DELAY_SECONDS);
        } catch (RequestException $exception) {
            // A rate limit or a server error retries later; any other refusal fails the send.
            $retryAfter = TelegramClient::retryAfter($exception);

            if ($retryAfter !== null || $exception->response->serverError()) {
                $this->retryLater(TelegramClient::errorDescription($exception), $retryAfter ?? self::RETRY_DELAY_SECONDS);

                return;
            }

            $this->fail(new RuntimeException(TelegramClient::errorDescription($exception)));
        }
    }

    public function failed(Throwable $exception): void
    {
        $owner = $this->post->workspace?->owner;

        if (! $owner) {
            return;
        }

        $lastError = Cache::pull($this->lastErrorKey());

        SendNotification::dispatch(
            user: $owner,
            workspaceId: $this->post->workspace_id,
            type: Type::TelegramSendFailed,
            title: __('notifications.telegram_send_failed.title'),
            body: __('notifications.telegram_send_failed.body', ['reason' => $lastError ?? $exception->getMessage()]),
            data: ['post_id' => $this->post->id],
        );
    }

    /**
     * The first enabled TikTok target's handoff, else the Post's videos,
     * photos and content.
     *
     * @return array<string, Closure(): void>
     *
     * @throws TikTokPublishException
     */
    private function steps(): array
    {
        $tikTokTarget = $this->post->postPlatforms()
            ->enabled()
            ->inComposerOrder()
            ->get()
            ->first(fn (PostPlatform $postPlatform): bool => $postPlatform->platform === Platform::TikTok);

        if ($tikTokTarget !== null) {
            return app(TikTokHandoff::class)->steps($tikTokTarget);
        }

        $telegramSteps = app(TelegramSteps::class);
        $media = $this->post->media_items;
        $mediaSteps = [...$telegramSteps->videos($media), ...$telegramSteps->photos($media)];

        if ($mediaSteps === []) {
            return [];
        }

        // Every kept Platform publishes the same plain text, so any of them sanitizes it.
        $content = filled($this->post->content)
            ? app(ContentSanitizer::class)->sanitize($this->post->content, Platform::Facebook)
            : '';

        return [...$mediaSteps, ...$telegramSteps->text($content)];
    }

    private function retryLater(string $reason, int $delaySeconds): void
    {
        Cache::put($this->lastErrorKey(), $reason, now()->addHours(self::LAST_ERROR_HOURS));

        $this->release($delaySeconds);
    }

    private function lastErrorKey(): string
    {
        return "telegram-send:{$this->sendId}:last-error";
    }
}
