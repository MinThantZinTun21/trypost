<?php

declare(strict_types=1);

use App\Support\Social\PublishCheckpoint;

test('tiktokPublishId reads a non-empty checkpoint', function () {
    expect(PublishCheckpoint::tiktokPublishId([
        PublishCheckpoint::TIKTOK_PUBLISH_ID => 'pub_in_flight',
    ]))->toBe('pub_in_flight')
        ->and(PublishCheckpoint::tiktokPublishId(['tiktok_publish_id' => '']))->toBeNull()
        ->and(PublishCheckpoint::tiktokPublishId(null))->toBeNull();
});

test('tiktokDerivativePaths returns an array or an empty list', function () {
    expect(PublishCheckpoint::tiktokDerivativePaths([
        PublishCheckpoint::TIKTOK_DERIVATIVE_PATHS => ['social-tiktok-photos/a.jpg'],
    ]))->toBe(['social-tiktok-photos/a.jpg'])
        ->and(PublishCheckpoint::tiktokDerivativePaths(['tiktok_derivative_paths' => 'invalid']))->toBe([])
        ->and(PublishCheckpoint::tiktokDerivativePaths(null))->toBe([]);
});

test('tiktokStatus reads a non-empty status', function () {
    expect(PublishCheckpoint::tiktokStatus([
        PublishCheckpoint::TIKTOK_STATUS => 'PROCESSING_DOWNLOAD',
    ]))->toBe('PROCESSING_DOWNLOAD')
        ->and(PublishCheckpoint::tiktokStatus(['tiktok_status' => '']))->toBeNull()
        ->and(PublishCheckpoint::tiktokStatus(null))->toBeNull();
});
