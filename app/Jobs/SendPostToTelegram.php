<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Post;
use App\Services\Telegram\PostMirrorText;
use App\Services\Telegram\TelegramClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a Post's Telegram mirror (ADR 0008) once it has published fully or
 * partly. `telegram_sent_at` marks it sent, so a second run sends nothing.
 */
class SendPostToTelegram implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public Post $post) {}

    public function handle(TelegramClient $telegram): void
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

        $telegram->sendMessage(PostMirrorText::for($post));

        $post->update(['telegram_sent_at' => now()]);
    }
}
