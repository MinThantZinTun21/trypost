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
            'liveStreamingNotEnabled' => __('live_streams.youtube_not_enabled'),
            'livePermissionBlocked' => __('live_streams.youtube_blocked'),
            default => $message,
        }, (int) $e->getCode(), $e);
    }
}
