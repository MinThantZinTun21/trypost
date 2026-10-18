<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Dto\MediaItem;
use App\Models\Media;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * The sends that put a Post's media and text in the Owner's Telegram channel,
 * shared by the TikTok handoff (ADR 0009) and Send to Telegram (ADR 0012).
 * A video goes out with its Download link as the caption (only the link when
 * it is over the Bot API's 50 MB upload limit); photos go out as albums; text
 * follows as its own messages, so it copies cleanly.
 *
 * Each send is a step keyed by what it sends. The keys that went out are
 * cached under the caller's progress key, so a retry after a refusal or a
 * long 429 never sends a video twice.
 */
class TelegramSteps
{
    /** Hours a partly sent delivery remembers which steps went out. */
    private const int PROGRESS_HOURS = 24;

    public function __construct(private TelegramClient $telegram) {}

    /**
     * Sends every step not yet sent under $progressKey, in order.
     *
     * @param  array<string, Closure(): void>  $steps
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function deliver(string $progressKey, array $steps): void
    {
        /** @var list<string> $sent */
        $sent = Cache::get($progressKey, []);

        foreach ($steps as $key => $step) {
            if (in_array($key, $sent, true)) {
                continue;
            }

            $step();
            $sent[] = $key;
            Cache::put($progressKey, $sent, now()->addHours(self::PROGRESS_HOURS));
        }

        Cache::forget($progressKey);
    }

    /**
     * Each video with its Download link as the caption, or the link alone
     * when the file is too big to upload or gone from storage.
     *
     * @param  Collection<int, MediaItem>  $media
     * @return array<string, Closure(): void>
     */
    public function videos(Collection $media): array
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
    public function photos(Collection $media): array
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
     * The text as messages within Telegram's limit, split between lines.
     *
     * @return array<string, Closure(): void>
     */
    public function text(string $text): array
    {
        return collect(TelegramClient::split($text))
            ->mapWithKeys(function (string $chunk, int $index): array {
                $chunkHash = md5($chunk);

                return ["caption:{$index}:{$chunkHash}" => fn () => $this->telegram->sendMessage($chunk)];
            })
            ->all();
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
