<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Notification\Type;
use App\Models\Post;
use App\Services\Telegram\PostMirror;
use App\Services\Telegram\TelegramClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends a Post's Telegram mirror (ADR 0008) once it has published fully or
 * partly. `telegram_sent_at` marks it sent, so a second run sends nothing.
 *
 * A 429 waits the `retry_after` Telegram asks for; any other refusal (bot not
 * an admin, wrong channel) fails at once, since a retry cannot fix it. The
 * last failure tells the Owner with a TelegramFailed Notification and leaves
 * the Post's status alone: the Post itself did publish.
 */
class SendPostToTelegram implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /** Several uploads of up to 50 MB each; stays under the queue's retry_after (960). */
    public int $timeout = 600;

    /** A Post deleted before the mirror ran needs no mirror. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Post $post) {}

    public function handle(TelegramClient $telegram, PostMirror $mirror): void
    {
        if (! $telegram->isConfigured()) {
            return;
        }

        $post = $this->post->fresh(['postPlatforms.socialAccount']);

        if (! $post instanceof Post || $post->telegram_sent_at !== null) {
            return;
        }

        if (! $post->status->isLive()) {
            return;
        }

        try {
            $mirror->send($post);
        } catch (RequestException $exception) {
            $retryAfter = TelegramClient::retryAfter($exception);

            if ($retryAfter !== null) {
                $this->release($retryAfter);

                return;
            }

            if ($exception->response->clientError()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        $post->update(['telegram_sent_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        $owner = $this->post->workspace?->owner;

        if (! $owner) {
            return;
        }

        SendNotification::dispatch(
            user: $owner,
            workspaceId: $this->post->workspace_id,
            type: Type::TelegramFailed,
            title: __('notifications.telegram_failed.title'),
            body: __('notifications.telegram_failed.body', [
                'reason' => Str::limit($exception ? TelegramClient::errorDescription($exception) : '', 200),
            ]),
            data: ['post_id' => $this->post->id],
        );
    }
}
