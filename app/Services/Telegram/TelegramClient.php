<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Dto\MediaItem;
use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

/**
 * Sends to the Owner's one Telegram channel through the Bot API
 * (https://core.telegram.org/bots/api). Not a Platform: there is no
 * Social account, only the bot token and channel id from config.
 */
class TelegramClient
{
    /** Bot API limit for a text message, in UTF-16 code units. */
    public const int MESSAGE_LIMIT = 4096;

    /** Bot API limit for a media caption, in UTF-16 code units. */
    public const int CAPTION_LIMIT = 1024;

    /** Largest photo the Bot API accepts as an upload, in bytes. */
    public const int PHOTO_UPLOAD_LIMIT = 10 * 1024 * 1024;

    /** Largest other file (video) the Bot API accepts as an upload, in bytes. */
    public const int FILE_UPLOAD_LIMIT = 50 * 1024 * 1024;

    /** Most items in one media group (album). */
    public const int ALBUM_LIMIT = 10;

    /** Most bytes one album request carries, so it uploads in time. */
    public const int ALBUM_BYTES_LIMIT = 50 * 1024 * 1024;

    /**
     * Longest 429 `retry_after` waited out in place; a longer one releases the
     * job. Kept short: the wait holds the one worker that also publishes Posts.
     */
    private const int RATE_LIMIT_MAX_WAIT = 10;

    /** 429s waited out in place for one call before giving up. */
    private const int RATE_LIMIT_RETRIES = 2;

    /** Seconds one upload may take: up to 50 MB leaves the server. */
    private const int UPLOAD_TIMEOUT = 300;

    /**
     * The reason Telegram gave for refusing a request (its `description`),
     * or the error's own message.
     */
    public static function errorDescription(Throwable $exception): string
    {
        if ($exception instanceof RequestException) {
            return (string) (data_get($exception->response->json(), 'description') ?? "HTTP {$exception->response->status()}");
        }

        return $exception->getMessage();
    }

    /**
     * Seconds Telegram asked to wait before the next call (429 Too Many
     * Requests, `parameters.retry_after`), or null for any other error.
     */
    public static function retryAfter(Throwable $exception): ?int
    {
        if (! $exception instanceof RequestException || ! $exception->response->tooManyRequests()) {
            return null;
        }

        return max(1, (int) data_get($exception->response->json(), 'parameters.retry_after', 1));
    }

    /**
     * Whether a refusal is worth retrying later: a rate limit or a server
     * error. Any other 4xx is Telegram refusing the call for good.
     */
    public static function isTransient(RequestException $exception): bool
    {
        return self::retryAfter($exception) !== null || $exception->response->serverError();
    }

    /**
     * Text length as Telegram counts it: UTF-16 code units, so an emoji
     * outside the Basic Multilingual Plane counts as two.
     */
    public static function length(string $text): int
    {
        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }

    /**
     * Splits text into chunks within the limit, breaking between lines so a
     * link is never cut in half; only a line longer than the limit is cut.
     *
     * @return list<string>
     */
    public static function split(string $text, int $limit = self::MESSAGE_LIMIT): array
    {
        $chunks = [];
        $current = null;

        foreach (explode("\n", $text) as $line) {
            // Each code point is at most two UTF-16 units, so half the limit always fits.
            $pieces = self::length($line) > $limit ? mb_str_split($line, intdiv($limit, 2)) : [$line];

            foreach ($pieces as $index => $piece) {
                $separator = $index === 0 ? "\n" : '';
                $candidate = $current === null ? $piece : "{$current}{$separator}{$piece}";

                if (self::length($candidate) <= $limit) {
                    $current = $candidate;

                    continue;
                }

                $chunks[] = $current;
                $current = $piece;
            }
        }

        $chunks[] = $current;

        return array_values(array_filter($chunks, fn (?string $chunk): bool => filled($chunk)));
    }

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
        foreach (self::split($text) as $chunk) {
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
     * A multipart request that streams each file from storage. Each try opens
     * fresh streams, since a retried request cannot reuse a read one.
     *
     * @param  array<string, MediaItem>  $files
     * @param  array<string, string>  $fields
     *
     * @throws RequestException
     */
    private function upload(string $method, array $files, array $fields): void
    {
        $this->waitingOutRateLimits(function () use ($method, $files, $fields): void {
            $request = Http::timeout(self::UPLOAD_TIMEOUT);
            $streams = [];

            try {
                foreach ($files as $name => $item) {
                    $stream = Storage::readStream($item->path);

                    if ($stream === null) {
                        throw new RuntimeException("Media file {$item->path} is missing from storage.");
                    }

                    $streams[] = $stream;
                    $request = $request->attach($name, $stream, $item->filename());
                }

                $request->post($this->endpoint($method), [
                    'chat_id' => (string) config('trypost.telegram.channel_id'),
                    ...$fields,
                ])->throw();
            } finally {
                foreach ($streams as $stream) {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws RequestException
     */
    private function call(string $method, array $payload): void
    {
        $this->waitingOutRateLimits(fn () => Http::asJson()
            ->post($this->endpoint($method), $payload)
            ->throw());
    }

    /**
     * Runs one Bot API call, waiting out a short 429 and trying the same call
     * again, so a rate limit never sends earlier parts of a handoff twice.
     *
     * @param  Closure(): mixed  $send
     *
     * @throws RequestException
     */
    private function waitingOutRateLimits(Closure $send): void
    {
        for ($try = 1; ; $try++) {
            try {
                $send();

                return;
            } catch (RequestException $exception) {
                $retryAfter = self::retryAfter($exception);

                if ($retryAfter === null || $retryAfter > self::RATE_LIMIT_MAX_WAIT || $try > self::RATE_LIMIT_RETRIES) {
                    throw $exception;
                }

                Sleep::for($retryAfter)->seconds();
            }
        }
    }

    private function endpoint(string $method): string
    {
        $api = config('trypost.telegram.api');
        $token = config('trypost.telegram.bot_token');

        return "{$api}/bot{$token}/{$method}";
    }
}
