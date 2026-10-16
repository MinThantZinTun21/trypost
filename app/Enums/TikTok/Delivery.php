<?php

declare(strict_types=1);

namespace App\Enums\TikTok;

/**
 * How a TikTok target is delivered (ADR 0009), from `TIKTOK_DELIVERY`:
 * uploaded through TikTok's Content Posting API, or sent to the Owner's
 * Telegram channel for them to post on TikTok by hand.
 */
enum Delivery: string
{
    case Api = 'api';
    case Telegram = 'telegram';

    public static function current(): self
    {
        return self::tryFrom((string) config('trypost.platforms.tiktok.delivery')) ?? self::Api;
    }

    public static function viaTelegram(): bool
    {
        return self::current() === self::Telegram;
    }
}
