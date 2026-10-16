<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends one message to the Telegram channel that TikTok posts go to
 * (ADR 0009), so the Owner can check the bot token, the channel id and the
 * bot's admin rights before a Post depends on them.
 */
class TestTelegramChannel extends Command
{
    protected $signature = 'telegram:test';

    protected $description = 'Send a test message to the Telegram channel';

    public function handle(TelegramClient $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->error('Telegram is not set up. Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHANNEL_ID in .env.');

            return self::FAILURE;
        }

        try {
            $telegram->sendMessage(__('telegram.test_message', ['app' => config('app.name')]));
        } catch (RequestException $exception) {
            $reason = TelegramClient::errorDescription($exception);
            $this->error("Telegram refused the message: {$reason}");
            $this->line($this->hint($exception->response->status()));

            return self::FAILURE;
        }

        $channel = config('trypost.telegram.channel_id');
        $this->info("Test message sent to {$channel}.");

        return self::SUCCESS;
    }

    private function hint(int $status): string
    {
        return match ($status) {
            Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND => 'Check TELEGRAM_BOT_TOKEN: it is the token @BotFather gave you.',
            Response::HTTP_FORBIDDEN => 'Add the bot to the channel as an administrator that can post messages.',
            Response::HTTP_BAD_REQUEST => 'Check TELEGRAM_CHANNEL_ID: a public channel is @username, a private one is its -100… id.',
            default => 'Try again in a minute; Telegram may be busy.',
        };
    }
}
