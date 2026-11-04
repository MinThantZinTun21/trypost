<?php

declare(strict_types=1);

test('supervisor runs the live worker on its own connection with no timeout', function (string $path) {
    expect(file_get_contents(base_path($path)))
        ->toContain('queue:work live --queue=live --tries=1 --timeout=0');
})->with([
    'docker/supervisord.prod.conf',
    'docker/supervisord.dev.conf',
]);

test('the image installs ffmpeg for live streams', function () {
    expect(file_get_contents(base_path('docker/Dockerfile')))->toMatch('/^\s+ffmpeg \\\\$/m');
});

test('the live connection never hands a running live stream to a second worker', function () {
    expect(config('queue.connections.live.retry_after'))->toBeGreaterThanOrEqual(60 * 60 * 24);
});
