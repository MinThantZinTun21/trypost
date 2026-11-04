<?php

declare(strict_types=1);

namespace App\Exceptions\Social;

use Google\Service\Exception as GoogleServiceException;
use RuntimeException;

/**
 * YouTube refused a Live Streaming API call; the message is YouTube's reason,
 * fit to show the Owner.
 */
class YouTubeLiveException extends RuntimeException
{
    public static function fromGoogleException(GoogleServiceException $e): self
    {
        $reason = (string) data_get($e->getErrors(), '0.reason', '');
        $message = (string) data_get($e->getErrors(), '0.message', $e->getMessage());

        return new self(match ($reason) {
            'liveStreamingNotEnabled' => 'The channel is not enabled for live streaming. Turn on live streaming in YouTube Studio (it can take 24 hours the first time).',
            'livePermissionBlocked' => 'The channel is blocked from live streaming. Check its standing in YouTube Studio.',
            default => $message,
        }, (int) $e->getCode(), $e);
    }
}
