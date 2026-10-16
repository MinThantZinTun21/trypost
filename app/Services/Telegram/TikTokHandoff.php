<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Dto\MediaItem;
use App\Enums\PostPlatform\ContentType;
use App\Exceptions\PlatformUnavailableException;
use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\TikTokPublishException;
use App\Models\Media;
use App\Models\PostPlatform;
use App\Services\Social\ContentSanitizer;
use App\Support\PostPlatformText;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Delivers a TikTok target to the Owner's Telegram channel instead of
 * TikTok (ADR 0009), for them to post by hand. A video goes out with its
 * Download link as the caption (only the link when it is over the Bot API's
 * 50 MB upload limit); photos go out as albums. The TikTok caption follows
 * as its own message, so it copies cleanly.
 *
 * Each send is a step keyed by what it sends. The keys that went out are
 * cached, so a retry after a refusal or a long 429 never sends a video twice.
 */
class TikTokHandoff
{
    /** Hours a partly sent handoff remembers which steps went out. */
    private const int PROGRESS_HOURS = 24;

    public function __construct(
        private TelegramClient $telegram,
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

        $progressKey = "tiktok-telegram:{$postPlatform->id}:sent-steps";
        /** @var list<string> $sent */
        $sent = Cache::get($progressKey, []);

        foreach ($this->steps($postPlatform) as $key => $step) {
            if (in_array($key, $sent, true)) {
                continue;
            }

            $this->send($step);
            $sent[] = $key;
            Cache::put($progressKey, $sent, now()->addHours(self::PROGRESS_HOURS));
        }

        Cache::forget($progressKey);

        return ['id' => '', 'url' => null];
    }

    /**
     * Runs one step and turns a Telegram failure into the publish job's terms:
     * a rate limit, a server error or a lost connection retries later; any
     * other refusal fails the target with Telegram's reason.
     *
     * @param  Closure(): void  $step
     */
    private function send(Closure $step): void
    {
        try {
            $step();
        } catch (ConnectionException $exception) {
            throw new PlatformUnavailableException($exception->getMessage());
        } catch (RequestException $exception) {
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
    }

    /**
     * @return array<string, Closure(): void>
     */
    private function steps(PostPlatform $postPlatform): array
    {
        $media = $postPlatform->post->media_items;
        $isPhotoPost = $postPlatform->content_type === ContentType::TikTokPhoto;

        $mediaSteps = $isPhotoPost ? $this->photoSteps($media) : $this->videoSteps($media);

        if ($mediaSteps === []) {
            throw new TikTokPublishException(
                userMessage: __($isPhotoPost ? 'telegram.no_photos' : 'telegram.no_video'),
                category: ErrorCategory::MediaFormat,
            );
        }

        return [...$mediaSteps, ...$this->captionSteps($this->caption($postPlatform, $isPhotoPost))];
    }

    /**
     * Each video with its Download link as the caption, or the link alone
     * when the file is too big to upload or gone from storage.
     *
     * @param  Collection<int, MediaItem>  $media
     * @return array<string, Closure(): void>
     */
    private function videoSteps(Collection $media): array
    {
        $videos = $media->filter(fn (MediaItem $item): bool => $item->isVideo());
        $existing = Media::query()->whereKey($videos->pluck('id')->all())->pluck('id');
        $steps = [];

        foreach ($videos as $item) {
            $link = $existing->contains($item->id) ? $this->downloadLink($item) : null;

            if ($this->uploadable($item, TelegramClient::FILE_UPLOAD_LIMIT)) {
                $steps["video:{$item->id}"] = fn () => $this->telegram->sendVideo($item, $link);
            } elseif ($link !== null) {
                $steps["link:{$item->id}"] = fn () => $this->telegram->sendMessage($link);
            }
        }

        return $steps;
    }

    /**
     * The photos in order, as albums of at most ALBUM_LIMIT items and
     * ALBUM_BYTES_LIMIT bytes.
     *
     * @param  Collection<int, MediaItem>  $media
     * @return array<string, Closure(): void>
     */
    private function photoSteps(Collection $media): array
    {
        $photos = $media
            ->filter(fn (MediaItem $item): bool => $item->isImage() && $this->uploadable($item, TelegramClient::PHOTO_UPLOAD_LIMIT))
            ->values();
        $albums = [];
        $album = [];
        $bytes = 0;

        foreach ($photos as $item) {
            $size = $this->size($item);

            if ($album !== [] && (count($album) === TelegramClient::ALBUM_LIMIT || $bytes + $size > TelegramClient::ALBUM_BYTES_LIMIT)) {
                $albums[] = $album;
                $album = [];
                $bytes = 0;
            }

            $album[] = $item;
            $bytes += $size;
        }

        if ($album !== []) {
            $albums[] = $album;
        }

        return collect($albums)->mapWithKeys(function (array $album): array {
            $ids = collect($album)->map(fn (MediaItem $item): string => (string) $item->id)->implode(',');

            return ["album:{$ids}" => fn () => count($album) > 1
                ? $this->telegram->sendMediaGroup($album)
                : $this->telegram->sendPhoto($album[0])];
        })->all();
    }

    /**
     * @return array<string, Closure(): void>
     */
    private function captionSteps(string $caption): array
    {
        return collect(TelegramClient::split($caption))
            ->mapWithKeys(function (string $chunk, int $index): array {
                $chunkHash = md5($chunk);

                return ["caption:{$index}:{$chunkHash}" => fn () => $this->telegram->sendMessage($chunk)];
            })
            ->all();
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

    /**
     * A permanent signed link (DownloadMediaController) that downloads the
     * video without signing in.
     */
    private function downloadLink(MediaItem $item): string
    {
        $label = __('telegram.download', ['name' => $item->filename()]);
        $url = URL::signedRoute('media.download', ['media' => $item->id]);

        return "{$label}: {$url}";
    }

    private function uploadable(MediaItem $item, int $limit): bool
    {
        return Storage::exists($item->path) && $this->size($item) <= $limit;
    }

    private function size(MediaItem $item): int
    {
        return $item->size ?? Storage::size($item->path);
    }
}
