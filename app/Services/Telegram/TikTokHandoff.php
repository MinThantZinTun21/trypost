<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Enums\PostPlatform\ContentType;
use App\Exceptions\PlatformUnavailableException;
use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\TikTokPublishException;
use App\Models\PostPlatform;
use App\Services\Social\ContentSanitizer;
use App\Support\PostPlatformText;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Delivers a TikTok target to the Owner's Telegram channel instead of
 * TikTok (ADR 0009), for them to post by hand: its video with a Download link
 * or its photos, then the TikTok caption as its own message (TelegramSteps).
 * A retry after a refusal or a long 429 never sends a video twice.
 */
class TikTokHandoff
{
    public function __construct(
        private TelegramClient $telegram,
        private TelegramSteps $telegramSteps,
        private ContentSanitizer $sanitizer,
    ) {}

    /**
     * @return array{id: string, url: null}
     *
     * @throws TikTokPublishException
     * @throws PlatformUnavailableException
     */
    public function publish(PostPlatform $postPlatform): array
    {
        if (! $this->telegram->isConfigured()) {
            throw new TikTokPublishException(
                userMessage: __('telegram.not_configured'),
                category: ErrorCategory::Permission,
            );
        }

        try {
            $this->telegramSteps->deliver("tiktok-telegram:{$postPlatform->id}:sent-steps", $this->steps($postPlatform));
        } catch (ConnectionException $exception) {
            throw new PlatformUnavailableException($exception->getMessage());
        } catch (RequestException $exception) {
            // A rate limit, a server error or a lost connection retries later; any other refusal fails the target.
            $retryAfter = TelegramClient::retryAfter($exception);

            if ($retryAfter !== null || $exception->response->serverError()) {
                throw new PlatformUnavailableException(
                    message: TelegramClient::errorDescription($exception),
                    httpStatus: $exception->response->status(),
                    retryDelaySeconds: $retryAfter,
                );
            }

            throw new TikTokPublishException(
                userMessage: __('telegram.refused', ['reason' => TelegramClient::errorDescription($exception)]),
                category: ErrorCategory::Permission,
                rawResponse: $exception->response->body(),
            );
        }

        return ['id' => '', 'url' => null];
    }

    /**
     * The target's sends: its video or photos by Content type, then its caption.
     *
     * @return array<string, Closure(): void>
     *
     * @throws TikTokPublishException
     */
    public function steps(PostPlatform $postPlatform): array
    {
        $media = $postPlatform->post->media_items;
        $isPhotoPost = $postPlatform->content_type === ContentType::TikTokPhoto;

        $mediaSteps = $isPhotoPost ? $this->telegramSteps->photos($media) : $this->telegramSteps->videos($media);

        if ($mediaSteps === []) {
            throw new TikTokPublishException(
                userMessage: __($isPhotoPost ? 'telegram.no_photos' : 'telegram.no_video'),
                category: ErrorCategory::MediaFormat,
            );
        }

        return [...$mediaSteps, ...$this->telegramSteps->text($this->caption($postPlatform, $isPhotoPost))];
    }

    /**
     * The text TikTokPublisher would post: a video's caption, or a photo
     * post's Title and Description; either falls back to the Post content.
     */
    private function caption(PostPlatform $postPlatform, bool $isPhotoPost): string
    {
        $meta = $postPlatform->meta ?? [];
        $content = filled($postPlatform->post->content)
            ? $this->sanitizer->sanitize($postPlatform->post->content, $postPlatform->platform)
            : null;

        if (! $isPhotoPost) {
            return PostPlatformText::trimmed($meta, 'caption') ?? trim((string) $content);
        }

        return collect([
            PostPlatformText::trimmed($meta, 'title'),
            PostPlatformText::trimmed($meta, 'description') ?? $content,
        ])->filter(fn (?string $part): bool => filled($part))->map(fn (string $part): string => trim($part))->implode("\n\n");
    }
}
