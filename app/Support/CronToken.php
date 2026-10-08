<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Credentials for GET /cron/run. The raw CRON_SECRET always works; a token is
 * `<expires-at unix time>.<HMAC of it under CRON_SECRET>`, so it can be handed
 * to a cron service and stops working on its own. Rotating CRON_SECRET revokes
 * every token issued under it.
 */
final class CronToken
{
    public static function issue(string $secret, CarbonInterface $expiresAt): string
    {
        $expires = (string) $expiresAt->getTimestamp();

        return "{$expires}.".self::sign($secret, $expires);
    }

    public static function accepts(string $secret, string $credential): bool
    {
        if ($secret === '' || $credential === '') {
            return false;
        }

        if (hash_equals($secret, $credential)) {
            return true;
        }

        [$expires, $signature] = array_pad(explode('.', $credential, 2), 2, '');

        if (! ctype_digit($expires) || (int) $expires <= now()->getTimestamp()) {
            return false;
        }

        return hash_equals(self::sign($secret, $expires), $signature);
    }

    private static function sign(string $secret, string $expires): string
    {
        return hash_hmac('sha256', "cron-token:{$expires}", $secret);
    }
}
