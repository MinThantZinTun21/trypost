<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Dto\MediaItem;
use App\Models\Post;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Plans one Post's Telegram mirror as an ordered list of Bot API calls: its
 * photos and videos within the upload limits (one at a time, or as albums of
 * up to ten), with the text as the caption when it fits and as following
 * messages when it does not. SendPostToTelegram runs the steps and remembers
 * how many went out, so a retry resumes instead of posting an album twice.
 */
class PostMirror
{
    public function __construct(private TelegramClient $telegram) {}

    /**
     * @return list<Closure(): void>
     */
    public function steps(Post $post): array
    {
        $text = PostMirrorText::for($post);
        $uploads = $this->uploads($post);

        if ($uploads->isEmpty()) {
            return $this->messages($text);
        }

        $caption = TelegramClient::length($text) <= TelegramClient::CAPTION_LIMIT ? $text : null;
        $steps = [];

        foreach ($this->albums($uploads) as $index => $album) {
            $steps[] = fn () => $this->sendAlbum($album, $index === 0 ? $caption : null);
        }

        return $caption === null ? [...$steps, ...$this->messages($text)] : $steps;
    }

    /**
     * @return list<Closure(): void>
     */
    private function messages(string $text): array
    {
        return array_map(
            fn (string $chunk): Closure => fn () => $this->telegram->sendMessage($chunk),
            TelegramClient::split($text),
        );
    }

    /**
     * @param  list<MediaItem>  $album
     */
    private function sendAlbum(array $album, ?string $caption): void
    {
        if (count($album) > 1) {
            $this->telegram->sendMediaGroup($album, $caption);

            return;
        }

        $item = $album[0];

        if ($item->isVideo()) {
            $this->telegram->sendVideo($item, $caption);
        } else {
            $this->telegram->sendPhoto($item, $caption);
        }
    }

    /**
     * Groups the uploads in order into albums of at most ALBUM_LIMIT items and
     * ALBUM_BYTES_LIMIT bytes, so one request never carries hundreds of MB.
     *
     * @param  Collection<int, MediaItem>  $uploads
     * @return list<list<MediaItem>>
     */
    private function albums(Collection $uploads): array
    {
        $albums = [];
        $album = [];
        $bytes = 0;

        foreach ($uploads as $item) {
            $size = $this->size($item);

            if ($album !== [] && (count($album) === TelegramClient::ALBUM_LIMIT || $bytes + $size > TelegramClient::ALBUM_BYTES_LIMIT)) {
                $albums[] = $album;
                $album = [];
                $bytes = 0;
            }

            $album[] = $item;
            $bytes += $size;
        }

        return [...$albums, $album];
    }

    /**
     * The photos and videos still in storage and small enough to upload, in
     * the Post's order. A missing or oversized file is left out, never fatal.
     *
     * @return Collection<int, MediaItem>
     */
    private function uploads(Post $post): Collection
    {
        return $post->media_items
            ->filter(function (MediaItem $item): bool {
                $limit = match (true) {
                    $item->isImage() => TelegramClient::PHOTO_UPLOAD_LIMIT,
                    $item->isVideo() => TelegramClient::FILE_UPLOAD_LIMIT,
                    default => null,
                };

                return $limit !== null && Storage::exists($item->path) && $this->size($item) <= $limit;
            })
            ->values();
    }

    private function size(MediaItem $item): int
    {
        return $item->size ?? Storage::size($item->path);
    }
}
