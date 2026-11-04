<?php

declare(strict_types=1);

use App\Exceptions\Social\YouTubeLiveException;
use Google\Service\Exception as GoogleServiceException;

test('it explains youtube\'s live streaming refusals to the owner', function (string $reason, string $message) {
    $exception = YouTubeLiveException::fromGoogleException(
        new GoogleServiceException('Forbidden', 403, null, [['reason' => $reason, 'message' => 'raw']]),
    );

    expect($exception->getMessage())->toBe($message);
})->with([
    'not enabled' => fn () => ['liveStreamingNotEnabled', __('live_streams.youtube_not_enabled')],
    'blocked' => fn () => ['livePermissionBlocked', __('live_streams.youtube_blocked')],
    'anything else' => ['quotaExceeded', 'raw'],
]);
