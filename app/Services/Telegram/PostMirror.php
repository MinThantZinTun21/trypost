<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Dto\MediaItem;
use App\Models\Post;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Sends one Post's Telegram mirror: its photos and videos within the Bot API
 * upload limits (one at a time, or as albums of up to ten), with the text as
 * the caption when it fits and as following messages when it does not.
 */
class PostMirror
{
    public function __construct(private TelegramClient $telegram) {}

    /**
     * @throws RequestException
     */
    public function send(Post $post): void
    {
        $text = PostMirrorText::for($post);
        $uploads = $this->uploads($post);

        if ($uploads->isEmpty()) {
            $this->telegram->sendMessage($text);

            return;
        }

        $caption = mb_strlen($text) <= TelegramClient::CAPTION_LIMIT ? $text : null;

        foreach ($uploads->chunk(TelegramClient::ALBUM_LIMIT)->values() as $index => $album) {
            $this->sendAlbum($album->values(), $index === 0 ? $caption : null);
        }

        if ($caption === null) {
            $this->telegram->sendMessage($text);
        }
    }

    /**
     * @param  Collection<int, MediaItem>  $album
     *
     * @throws RequestException
     */
    private function sendAlbum(Collection $album, ?string $caption): void
    {
        if ($album->count() > 1) {
            $this->telegram->sendMediaGroup($album->all(), $caption);

            return;
        }

        $item = $album->first();

        if ($item->isVideo()) {
            $this->telegram->sendVideo($item, $caption);
        } else {
            $this->telegram->sendPhoto($item, $caption);
        }
    }

    /**
     * The photos and videos small enough to upload, in the Post's order.
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

                return $limit !== null && $this->size($item) <= $limit;
            })
            ->values();
    }

    private function size(MediaItem $item): int
    {
        return $item->size ?? Storage::size($item->path);
    }
}
