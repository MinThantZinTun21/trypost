<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Sends to the Owner's one Telegram channel through the Bot API
 * (https://core.telegram.org/bots/api). Not a Platform: there is no
 * Social account, only the bot token and channel id from config.
 */
class TelegramClient
{
    /** Bot API limit for a text message, in characters. */
    public const int MESSAGE_LIMIT = 4096;

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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    private function call(string $method, array $payload): array
    {
        $api = config('trypost.telegram.api');
        $token = config('trypost.telegram.bot_token');

        return Http::asJson()
            ->post("{$api}/bot{$token}/{$method}", $payload)
            ->throw()
            ->json();
    }
}
