<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Dto\MediaItem;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Sends to the Owner's one Telegram channel through the Bot API
 * (https://core.telegram.org/bots/api). Not a Platform: there is no
 * Social account, only the bot token and channel id from config.
 */
class TelegramClient
{
    /** Bot API limit for a text message, in characters. */
    public const int MESSAGE_LIMIT = 4096;

    /** Bot API limit for a media caption, in characters. */
    public const int CAPTION_LIMIT = 1024;

    /** Largest photo the Bot API accepts as an upload, in bytes. */
    public const int PHOTO_UPLOAD_LIMIT = 10 * 1024 * 1024;

    /** Largest other file (video) the Bot API accepts as an upload, in bytes. */
    public const int FILE_UPLOAD_LIMIT = 50 * 1024 * 1024;

    /** Most items in one media group (album). */
    public const int ALBUM_LIMIT = 10;

    /** Seconds one upload may take: up to 50 MB leaves the server. */
    private const int UPLOAD_TIMEOUT = 300;

    public function isConfigured(): bool
    {
        return filled(config('trypost.telegram.bot_token'))
            && filled(config('trypost.telegram.channel_id'));
    }

    /**
     * Sends the text as one message, or as several when it is over the limit.
     *
     * @throws RequestException
     */
    public function sendMessage(string $text): void
    {
        foreach (mb_str_split($text, self::MESSAGE_LIMIT) as $chunk) {
            $this->call('sendMessage', [
                'chat_id' => config('trypost.telegram.channel_id'),
                'text' => $chunk,
            ]);
        }
    }

    /**
     * @throws RequestException
     */
    public function sendPhoto(MediaItem $item, ?string $caption = null): void
    {
        $this->upload('sendPhoto', ['photo' => $item], $this->captionFields($caption));
    }

    /**
     * @throws RequestException
     */
    public function sendVideo(MediaItem $item, ?string $caption = null): void
    {
        $this->upload('sendVideo', ['video' => $item], [
            'supports_streaming' => 'true',
            ...$this->captionFields($caption),
        ]);
    }

    /**
     * Sends 2 to 10 photos and videos as one album, the caption on the first.
     *
     * @param  list<MediaItem>  $items
     *
     * @throws RequestException
     */
    public function sendMediaGroup(array $items, ?string $caption = null): void
    {
        $media = [];
        $files = [];

        foreach (array_values($items) as $index => $item) {
            $media[] = [
                'type' => $item->isVideo() ? 'video' : 'photo',
                'media' => "attach://file{$index}",
                ...($index === 0 ? $this->captionFields($caption) : []),
            ];
            $files["file{$index}"] = $item;
        }

        $this->upload('sendMediaGroup', $files, ['media' => json_encode($media, JSON_THROW_ON_ERROR)]);
    }

    /**
     * @return array<string, string>
     */
    private function captionFields(?string $caption): array
    {
        return filled($caption) ? ['caption' => $caption] : [];
    }

    /**
     * A multipart request that streams each file from storage.
     *
     * @param  array<string, MediaItem>  $files
     * @param  array<string, string>  $fields
     *
     * @throws RequestException
     */
    private function upload(string $method, array $files, array $fields): void
    {
        $request = Http::timeout(self::UPLOAD_TIMEOUT);

        foreach ($files as $name => $item) {
            $stream = Storage::readStream($item->path);

            if ($stream === null) {
                throw new RuntimeException("Media file {$item->path} is missing from storage.");
            }

            $request = $request->attach($name, $stream, $item->original_filename ?: basename($item->path));
        }

        $request->post($this->endpoint($method), [
            'chat_id' => (string) config('trypost.telegram.channel_id'),
            ...$fields,
        ])->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    private function call(string $method, array $payload): array
    {
        return Http::asJson()
            ->post($this->endpoint($method), $payload)
            ->throw()
            ->json();
    }

    private function endpoint(string $method): string
    {
        $api = config('trypost.telegram.api');
        $token = config('trypost.telegram.bot_token');

        return "{$api}/bot{$token}/{$method}";
    }
}
